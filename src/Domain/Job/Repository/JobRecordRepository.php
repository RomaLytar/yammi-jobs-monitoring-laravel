<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Repository;

use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
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

    /**
     * Return records matching the given filters, paginated and ordered
     * newest first. The search string is matched against job_class
     * (case-insensitive substring).
     *
     * @return array<JobRecord>
     */
    public function findPaginated(
        ?\DateTimeImmutable $since,
        ?string $search,
        int $perPage,
        int $page,
        string $sortBy = 'started_at',
        string $sortDirection = 'desc',
        ?JobStatus $statusFilter = null,
    ): array;

    /**
     * Return the total number of records matching the given filters.
     */
    public function countFiltered(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?JobStatus $statusFilter = null,
    ): int;

    /**
     * Return status breakdown for records matching the given filters.
     *
     * @return array{total: int, processed: int, failed: int, processing: int}
     */
    public function statusCounts(?\DateTimeImmutable $since, ?string $search): array;
}
