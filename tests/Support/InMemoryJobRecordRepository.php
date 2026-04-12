<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
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

    private function key(JobIdentifier $id, Attempt $attempt): string
    {
        return $id->value.'#'.$attempt->value;
    }
}
