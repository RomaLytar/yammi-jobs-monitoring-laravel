<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Repository;

use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Persistence boundary for the JobRecord aggregate.
 *
 * Lives in the Domain layer per the dependency rule. Concrete
 * implementations belong in Infrastructure.
 */
interface JobRecordRepository
{
    /**
     * Insert a new record, or replace an existing one identified by the
     * same (id, attempt) pair. The operation MUST be atomic with respect
     * to that pair.
     */
    public function save(JobRecord $record): void;

    /**
     * Return the previously stored record for the given identifier and
     * attempt, or null if none has been stored yet.
     */
    public function findByIdentifierAndAttempt(
        JobIdentifier $id,
        Attempt $attempt,
    ): ?JobRecord;

    /**
     * Return the most recent records, ordered newest first.
     *
     * @return array<JobRecord>
     */
    public function findRecent(int $limit): array;

    /**
     * Return failed records from the last N hours, ordered newest first.
     *
     * @return array<JobRecord>
     */
    public function findRecentFailures(int $hours): array;

    /**
     * Return aggregate statistics for a given job class.
     *
     * @return array{total: int, processed: int, failed: int, avg_duration_ms: float|null}
     */
    public function aggregateStatsByClass(string $jobClass): array;
}
