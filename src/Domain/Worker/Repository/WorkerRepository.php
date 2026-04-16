<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Worker\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

/**
 * Persistence boundary for the Worker aggregate.
 *
 * Implementations live in Infrastructure and MUST perform the upsert in
 * {@see recordHeartbeat()} atomically on the worker identifier — two
 * concurrent writes for the same worker must end up as one row.
 */
interface WorkerRepository
{
    /**
     * Insert or replace the heartbeat for the given worker. Atomic on
     * the worker identifier.
     */
    public function recordHeartbeat(WorkerHeartbeat $heartbeat): void;

    /**
     * Mark the worker as intentionally stopped. Called when Laravel
     * emits `WorkerStopping`.
     */
    public function markStopped(WorkerIdentifier $id, DateTimeImmutable $stoppedAt): void;

    /**
     * Return the worker identified by the given id, or null if none.
     */
    public function find(WorkerIdentifier $id): ?Worker;

    /**
     * Return every known worker, newest heartbeat first.
     *
     * @return list<Worker>
     */
    public function findAll(): array;

    /**
     * Return a paginated slice of workers, newest heartbeat first.
     *
     * @return list<Worker>
     */
    public function findPaginated(int $perPage, int $page): array;

    /**
     * Total count of stored workers. Used alongside findPaginated.
     */
    public function countAll(): int;

    /**
     * Return the workers whose latest heartbeat is older than the
     * given cutoff AND which were not marked stopped. Used by the
     * silent-worker detector.
     *
     * @return list<Worker>
     */
    public function findSilentSince(DateTimeImmutable $cutoff): array;

    /**
     * Count currently-alive workers grouped by `connection:queue`.
     * A worker counts as alive when its last heartbeat is at or after
     * `$aliveSince` and it has not been marked stopped.
     *
     * @return array<string, int> keyed by queueKey (connection:queue)
     */
    public function countAliveByQueueKey(DateTimeImmutable $aliveSince): array;

    /**
     * Delete every heartbeat row older than the given cutoff. Used by
     * the prune command to keep the table bounded.
     *
     * @return int Number of deleted rows
     */
    public function deleteOlderThan(DateTimeImmutable $before): int;
}
