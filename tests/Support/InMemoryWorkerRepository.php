<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

final class InMemoryWorkerRepository implements WorkerRepository
{
    /**
     * @var array<string, WorkerHeartbeat>
     */
    private array $heartbeats = [];

    /**
     * @var array<string, DateTimeImmutable>
     */
    private array $stopped = [];

    public function recordHeartbeat(WorkerHeartbeat $heartbeat): void
    {
        $key = $heartbeat->workerId->value;
        $this->heartbeats[$key] = $heartbeat;
        unset($this->stopped[$key]);
    }

    public function markStopped(WorkerIdentifier $id, DateTimeImmutable $stoppedAt): void
    {
        if (! isset($this->heartbeats[$id->value])) {
            return;
        }

        $this->stopped[$id->value] = $stoppedAt;
    }

    public function find(WorkerIdentifier $id): ?Worker
    {
        if (! isset($this->heartbeats[$id->value])) {
            return null;
        }

        return new Worker(
            heartbeat: $this->heartbeats[$id->value],
            stoppedAt: $this->stopped[$id->value] ?? null,
        );
    }

    public function findAll(): array
    {
        return $this->paginateFromHeartbeats(PHP_INT_MAX, 1);
    }

    public function findPaginated(int $perPage, int $page): array
    {
        return $this->paginateFromHeartbeats($perPage, $page);
    }

    public function countAll(): int
    {
        return count($this->heartbeats);
    }

    public function findSilentSince(DateTimeImmutable $cutoff): array
    {
        $result = [];
        foreach ($this->heartbeats as $key => $heartbeat) {
            if (isset($this->stopped[$key])) {
                continue;
            }

            if ($heartbeat->lastSeenAt < $cutoff) {
                $result[] = new Worker(heartbeat: $heartbeat);
            }
        }

        return $result;
    }

    public function countAliveByQueueKey(DateTimeImmutable $aliveSince): array
    {
        $counts = [];
        foreach ($this->heartbeats as $key => $heartbeat) {
            if (isset($this->stopped[$key])) {
                continue;
            }

            if ($heartbeat->lastSeenAt < $aliveSince) {
                continue;
            }

            $qk = $heartbeat->queueKey();
            $counts[$qk] = ($counts[$qk] ?? 0) + 1;
        }

        return $counts;
    }

    public function deleteOlderThan(DateTimeImmutable $before): int
    {
        $deleted = 0;
        foreach ($this->heartbeats as $key => $heartbeat) {
            if ($heartbeat->lastSeenAt < $before) {
                unset($this->heartbeats[$key], $this->stopped[$key]);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return list<Worker>
     */
    private function paginateFromHeartbeats(int $perPage, int $page): array
    {
        $ordered = $this->heartbeats;
        uasort(
            $ordered,
            fn (WorkerHeartbeat $a, WorkerHeartbeat $b) => $b->lastSeenAt <=> $a->lastSeenAt,
        );

        $slice = array_slice(
            $ordered,
            max(0, ($page - 1) * $perPage),
            $perPage,
            preserve_keys: true,
        );

        $result = [];
        foreach ($slice as $key => $heartbeat) {
            $result[] = new Worker(
                heartbeat: $heartbeat,
                stoppedAt: $this->stopped[$key] ?? null,
            );
        }

        return $result;
    }
}
