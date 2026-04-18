<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

<<<<<<< HEAD
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
=======
>>>>>>> origin/main
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

<<<<<<< HEAD
    /**
     * @var array<string, string>
     */
    private array $fingerprints = [];

    /**
     * @var array<string, \Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport>
     */
    private array $outcomes = [];

=======
>>>>>>> origin/main
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

    public function aggregateStatsByClassMulti(?\DateTimeImmutable $since): array
    {
        $matching = array_filter(
            $this->records,
            static fn (JobRecord $r) => $since === null || $r->startedAt >= $since,
        );

        /** @var array<string, array{total: int, processed: int, failed: int, duration_sum: int, duration_count: int, max_duration: int, retry_count: int}> $groups */
        $groups = [];

        foreach ($matching as $record) {
            $class = $record->jobClass;

            if (! isset($groups[$class])) {
                $groups[$class] = [
                    'total' => 0,
                    'processed' => 0,
                    'failed' => 0,
                    'duration_sum' => 0,
                    'duration_count' => 0,
                    'max_duration' => 0,
                    'retry_count' => 0,
                ];
            }

            $groups[$class]['total']++;

            if ($record->status() === JobStatus::Processed) {
                $groups[$class]['processed']++;
            } elseif ($record->status() === JobStatus::Failed) {
                $groups[$class]['failed']++;
            }

            $duration = $record->duration();
            if ($duration !== null) {
                $groups[$class]['duration_sum'] += $duration->milliseconds;
                $groups[$class]['duration_count']++;
                $groups[$class]['max_duration'] = max($groups[$class]['max_duration'], $duration->milliseconds);
            }

            if ($record->attempt->value > 1) {
                $groups[$class]['retry_count']++;
            }
        }

        $result = [];
        foreach ($groups as $class => $g) {
            $result[] = [
                'job_class' => $class,
                'total' => $g['total'],
                'processed' => $g['processed'],
                'failed' => $g['failed'],
                'avg_duration_ms' => $g['duration_count'] > 0 ? (float) ($g['duration_sum'] / $g['duration_count']) : null,
                'max_duration_ms' => $g['duration_count'] > 0 ? $g['max_duration'] : null,
                'retry_count' => $g['retry_count'],
            ];
        }

        usort($result, static fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return $result;
    }

    public function findDeadLetterJobs(int $perPage, int $page, int $maxTries): array
    {
        $dead = $this->deadLetterRecords($maxTries);

        usort($dead, static fn (JobRecord $a, JobRecord $b) => $b->startedAt <=> $a->startedAt);

        return array_slice($dead, ($page - 1) * $perPage, $perPage);
    }

    public function countDeadLetterJobs(int $maxTries): int
    {
        return count($this->deadLetterRecords($maxTries));
    }

    public function deleteByIdentifier(JobIdentifier $id): int
    {
        $count = 0;

        foreach ($this->records as $key => $record) {
            if ($record->id->value === $id->value) {
                unset($this->records[$key]);
                $count++;
            }
        }

        return $count;
    }

    public function listDeadLetterUuids(int $maxTries, int $limit): array
    {
        $dead = $this->deadLetterRecords($maxTries);

        usort($dead, static fn (JobRecord $a, JobRecord $b) => $b->startedAt <=> $a->startedAt);

        $uuids = [];
        foreach ($dead as $record) {
            $uuids[$record->id->value] = true;
            if (count($uuids) >= $limit) {
                break;
            }
        }

        return array_keys($uuids);
    }

    public function listFailureUuids(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?string $queueFilter,
        ?string $connectionFilter,
        ?FailureCategory $failureCategoryFilter,
        int $limit,
    ): array {
        $records = $this->applyFilters(
            $since,
            $search,
            JobStatus::Failed,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        usort($records, static fn (JobRecord $a, JobRecord $b) => $b->startedAt <=> $a->startedAt);

        $uuids = [];
        foreach ($records as $record) {
            $uuids[$record->id->value] = true;
            if (count($uuids) >= $limit) {
                break;
            }
        }

        return array_keys($uuids);
    }

    /**
     * @return list<JobRecord>
     */
    private function deadLetterRecords(int $maxTries): array
    {
        // Group by uuid, keep only the latest-attempt record per uuid.
        $latestPerUuid = [];
        foreach ($this->records as $record) {
            $uuid = $record->id->value;
            if (! isset($latestPerUuid[$uuid]) || $record->attempt->value > $latestPerUuid[$uuid]->attempt->value) {
                $latestPerUuid[$uuid] = $record;
            }
        }

        $dead = [];
        foreach ($latestPerUuid as $record) {
            if ($record->status() !== JobStatus::Failed) {
                continue;
            }

            $category = $record->failureCategory();
            $categoryIsTerminal = $category === FailureCategory::Permanent || $category === FailureCategory::Critical;
            $attemptsExhausted = $record->attempt->value >= $maxTries;

            if ($categoryIsTerminal || $attemptsExhausted) {
                $dead[] = $record;
            }
        }

        return array_values($dead);
    }

    public function findAllAttempts(JobIdentifier $id): array
    {
        $matching = array_values(array_filter(
            $this->records,
            static fn (JobRecord $r) => $r->id->value === $id->value,
        ));

        usort(
            $matching,
            static fn (JobRecord $a, JobRecord $b) => $a->attempt->value <=> $b->attempt->value,
        );

        return $matching;
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

    public function countFailuresSince(\DateTimeImmutable $since, ?int $minAttempt = null): int
    {
        return count(array_filter(
            $this->records,
            fn (JobRecord $r) => $this->matchesFailureWindow($r, $since, $minAttempt),
        ));
    }

    public function countFailuresByCategorySince(
        FailureCategory $category,
        \DateTimeImmutable $since,
        ?int $minAttempt = null,
    ): int {
        return count(array_filter(
            $this->records,
            fn (JobRecord $r) => $this->matchesFailureWindow($r, $since, $minAttempt)
                && $r->failureCategory() === $category,
        ));
    }

    public function countFailuresByClassSince(
        string $jobClass,
        \DateTimeImmutable $since,
        ?int $minAttempt = null,
    ): int {
        return count(array_filter(
            $this->records,
            fn (JobRecord $r) => $this->matchesFailureWindow($r, $since, $minAttempt)
                && $r->jobClass === $jobClass,
        ));
    }

    public function findFailureSamples(
        \DateTimeImmutable $since,
        int $limit,
        ?int $minAttempt = null,
        ?FailureCategory $category = null,
        ?string $jobClass = null,
    ): array {
        $matching = array_filter(
            $this->records,
            fn (JobRecord $r) => $this->matchesFailureWindow($r, $since, $minAttempt)
                && ($category === null || $r->failureCategory() === $category)
                && ($jobClass === null || $r->jobClass === $jobClass),
        );

        usort(
            $matching,
            static fn (JobRecord $a, JobRecord $b) => $b->finishedAt() <=> $a->finishedAt(),
        );

        return array_slice(array_values($matching), 0, $limit);
    }

    public function aggregateTimeBuckets(
        \DateTimeImmutable $since,
        string $bucketSize,
    ): array {
        $format = match ($bucketSize) {
            'minute' => 'Y-m-d\TH:i:00\Z',
            'hour' => 'Y-m-d\TH:00:00\Z',
            'day' => 'Y-m-d\T00:00:00\Z',
            default => throw new \InvalidArgumentException("Unsupported bucket size: {$bucketSize}"),
        };

        $utc = new \DateTimeZone('UTC');
        $buckets = [];

        foreach ($this->records as $record) {
            $status = $record->status();
            if ($status !== JobStatus::Processed && $status !== JobStatus::Failed) {
                continue;
            }

            if ($record->startedAt < $since) {
                continue;
            }

            $bucket = $record->startedAt->setTimezone($utc)->format($format);

            if (! isset($buckets[$bucket])) {
                $buckets[$bucket] = ['bucket' => $bucket, 'processed' => 0, 'failed' => 0];
            }

            if ($status === JobStatus::Processed) {
                $buckets[$bucket]['processed']++;
            } else {
                $buckets[$bucket]['failed']++;
            }
        }

        ksort($buckets);

        return array_values($buckets);
    }

    private function matchesFailureWindow(JobRecord $r, \DateTimeImmutable $since, ?int $minAttempt): bool
    {
        if ($r->status() !== JobStatus::Failed) {
            return false;
        }

        if ($r->finishedAt() === null || $r->finishedAt() < $since) {
            return false;
        }

        if ($minAttempt !== null && $r->attempt->value < $minAttempt) {
            return false;
        }

        return true;
    }

    private function key(JobIdentifier $id, Attempt $attempt): string
    {
        return $id->value.'#'.$attempt->value;
    }
<<<<<<< HEAD

    public function setFingerprint(
        JobIdentifier $id,
        Attempt $attempt,
        FailureFingerprint $fingerprint,
    ): void {
        $this->fingerprints[$this->key($id, $attempt)] = $fingerprint->hash;
    }

    public function fingerprintFor(JobIdentifier $id, Attempt $attempt): ?string
    {
        return $this->fingerprints[$this->key($id, $attempt)] ?? null;
    }

    public function listUuidsByFingerprint(FailureFingerprint $fingerprint, int $limit, int $offset = 0): array
    {
        $uuids = [];

        foreach ($this->fingerprints as $key => $hash) {
            if ($hash !== $fingerprint->hash) {
                continue;
            }
            [$uuid] = explode('#', $key, 2);
            $uuids[$uuid] = true;
        }

        return array_slice(array_keys($uuids), $offset, $limit);
    }

    public function countFailuresByFingerprintSince(\DateTimeImmutable $since, int $minCount): array
    {
        $counts = [];

        foreach ($this->records as $key => $record) {
            if ($record->status() !== JobStatus::Failed) {
                continue;
            }
            $finished = $record->finishedAt();
            if ($finished === null || $finished < $since) {
                continue;
            }

            $hash = $this->fingerprints[$key] ?? null;
            if ($hash === null) {
                continue;
            }

            $counts[$hash] = ($counts[$hash] ?? 0) + 1;
        }

        return array_filter($counts, static fn (int $c) => $c >= $minCount);
    }

    public function recordProgress(
        JobIdentifier $id,
        Attempt $attempt,
        \Yammi\JobsMonitor\Domain\Job\ValueObject\JobProgress $progress,
    ): void {
        // In-memory stub: unit tests don't verify progress persistence.
    }

    public function recordOutcome(
        JobIdentifier $id,
        Attempt $attempt,
        \Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport $outcome,
    ): void {
        $this->outcomes[$this->key($id, $attempt)] = $outcome;
    }

    public function outcomeFor(JobIdentifier $id, Attempt $attempt): ?\Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport
    {
        return $this->outcomes[$this->key($id, $attempt)] ?? null;
    }

    public function countPartialCompletionsSince(\DateTimeImmutable $since): int
    {
        return 0;
    }

    public function countZeroProcessedSince(\DateTimeImmutable $since): int
    {
        return 0;
    }
=======
>>>>>>> origin/main
}
