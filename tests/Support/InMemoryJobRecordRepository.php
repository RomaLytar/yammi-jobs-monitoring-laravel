<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * In-memory test double for the JobRecordRepository contract. Used by
 * unit tests of Application actions and as the contract verification
 * target for the InMemoryJobRecordRepositoryTest.
 */
final class InMemoryJobRecordRepository implements JobRecordRepository
{
    /**
     * @var array<string, JobRecord>
     */
    private array $records = [];

    public function save(JobRecord $record): void
    {
        $this->records[$this->key($record->id, $record->attempt)] = $record;
    }

    public function findByIdentifierAndAttempt(
        JobIdentifier $id,
        Attempt $attempt,
    ): ?JobRecord {
        return $this->records[$this->key($id, $attempt)] ?? null;
    }

    public function findRecent(int $limit): array
    {
        $sorted = $this->records;
        usort($sorted, static fn (JobRecord $a, JobRecord $b) => $b->startedAt <=> $a->startedAt);

        return array_slice($sorted, 0, $limit);
    }

    public function findRecentFailures(int $hours): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        $failures = array_filter(
            $this->records,
            static fn (JobRecord $r) => $r->status() === JobStatus::Failed && $r->startedAt >= $since,
        );

        usort($failures, static fn (JobRecord $a, JobRecord $b) => $b->startedAt <=> $a->startedAt);

        return array_values($failures);
    }

    /**
     * @return array{total: int, processed: int, failed: int, avg_duration_ms: float|null}
     */
    public function aggregateStatsByClass(string $jobClass): array
    {
        $matching = array_filter(
            $this->records,
            static fn (JobRecord $r) => $r->jobClass === $jobClass,
        );

        $total = count($matching);
        $processed = 0;
        $failed = 0;
        $durationSum = 0;
        $durationCount = 0;

        foreach ($matching as $record) {
            if ($record->status() === JobStatus::Processed) {
                $processed++;
            } elseif ($record->status() === JobStatus::Failed) {
                $failed++;
            }

            $duration = $record->duration();
            if ($duration !== null) {
                $durationSum += $duration->milliseconds;
                $durationCount++;
            }
        }

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'avg_duration_ms' => $durationCount > 0 ? (float) ($durationSum / $durationCount) : null,
        ];
    }

    public function findPaginated(
        ?\DateTimeImmutable $since,
        ?string $search,
        int $perPage,
        int $page,
        string $sortBy = 'started_at',
        string $sortDirection = 'desc',
        ?JobStatus $statusFilter = null,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): array {
        $filtered = $this->applyFilters(
            $since,
            $search,
            $statusFilter,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        usort($filtered, static function (JobRecord $a, JobRecord $b) use ($sortBy, $sortDirection): int {
            $result = match ($sortBy) {
                'status' => $a->status()->value <=> $b->status()->value,
                'duration_ms' => ($a->duration()?->milliseconds ?? 0) <=> ($b->duration()?->milliseconds ?? 0),
                'job_class' => $a->jobClass <=> $b->jobClass,
                default => $a->startedAt <=> $b->startedAt,
            };

            return $sortDirection === 'asc' ? $result : -$result;
        });

        $offset = ($page - 1) * $perPage;

        return array_slice($filtered, $offset, $perPage);
    }

    public function countFiltered(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?JobStatus $statusFilter = null,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): int {
        return count($this->applyFilters(
            $since,
            $search,
            $statusFilter,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        ));
    }

    /**
     * @return array{total: int, processed: int, failed: int, processing: int}
     */
    public function statusCounts(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): array {
        $filtered = $this->applyFilters(
            $since,
            $search,
            null,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        $processed = 0;
        $failed = 0;
        $processing = 0;

        foreach ($filtered as $record) {
            if ($record->status() === JobStatus::Processed) {
                $processed++;
            } elseif ($record->status() === JobStatus::Failed) {
                $failed++;
            } elseif ($record->status() === JobStatus::Processing) {
                $processing++;
            }
        }

        return [
            'total' => count($filtered),
            'processed' => $processed,
            'failed' => $failed,
            'processing' => $processing,
        ];
    }

    public function distinctQueues(): array
    {
        $queues = [];
        foreach ($this->records as $record) {
            $queues[$record->queue->value] = true;
        }

        return array_keys($queues);
    }

    public function distinctConnections(): array
    {
        $connections = [];
        foreach ($this->records as $record) {
            $connections[$record->connection] = true;
        }

        return array_keys($connections);
    }

    /**
     * @return array<JobRecord>
     */
    private function applyFilters(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?JobStatus $statusFilter,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): array {
        return array_values(array_filter(
            $this->records,
            static function (JobRecord $r) use (
                $since,
                $search,
                $statusFilter,
                $queueFilter,
                $connectionFilter,
                $failureCategoryFilter,
            ): bool {
                if ($since !== null && $r->startedAt < $since) {
                    return false;
                }

                if ($search !== null && $search !== '' && stripos($r->jobClass, $search) === false) {
                    return false;
                }

                if ($statusFilter !== null && $r->status() !== $statusFilter) {
                    return false;
                }

                if ($queueFilter !== null && $queueFilter !== '' && $r->queue->value !== $queueFilter) {
                    return false;
                }

                if ($connectionFilter !== null && $connectionFilter !== '' && $r->connection !== $connectionFilter) {
                    return false;
                }

                if ($failureCategoryFilter !== null && $r->failureCategory() !== $failureCategoryFilter) {
                    return false;
                }

                return true;
            },
        ));
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        $count = 0;

        foreach ($this->records as $key => $record) {
            if ($record->startedAt < $before) {
                unset($this->records[$key]);
                $count++;
            }
        }

        return $count;
    }

    private function key(JobIdentifier $id, Attempt $attempt): string
    {
        return $id->value.'#'.$attempt->value;
    }
}
