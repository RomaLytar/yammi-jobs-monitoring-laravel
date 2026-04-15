<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Scheduler\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;

interface ScheduledTaskRunRepository
{
    public function save(ScheduledTaskRun $run): void;

    public function findRunning(string $mutex, DateTimeImmutable $startedAt): ?ScheduledTaskRun;

    /**
     * @return iterable<ScheduledTaskRun>
     */
    public function findStuckRunning(DateTimeImmutable $olderThan): iterable;

    public function countFailedSince(DateTimeImmutable $since): int;

    public function countLateSince(DateTimeImmutable $since): int;

    /**
     * Last known run per mutex, whatever its status. Used by the dashboard
     * and by the late-run watchdog to resolve "when was this task last seen?"
     *
     * @return array<string, ScheduledTaskRun>
     */
    public function latestRunPerMutex(): array;

    /**
     * Paginated, filtered, sorted list of runs for the dashboard table.
     *
     * @param  array{status?: string, search?: string, sort?: string, dir?: string}  $filters
     * @return array{rows: list<ScheduledTaskRun>, total: int}
     */
    public function findPaginated(int $perPage, int $page, array $filters): array;

    /**
     * @return array<string, int> map of status value → count
     */
    public function statusCounts(): array;
}
