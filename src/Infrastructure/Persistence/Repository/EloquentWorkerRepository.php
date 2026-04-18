<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\WorkerHeartbeatModel;

final class EloquentWorkerRepository implements WorkerRepository
{
    public function recordHeartbeat(WorkerHeartbeat $heartbeat): void
    {
        WorkerHeartbeatModel::query()->updateOrCreate(
            ['worker_id' => $heartbeat->workerId->value],
            [
                'connection' => $heartbeat->connection,
                'queue' => $heartbeat->queue,
                'host' => $heartbeat->host,
                'pid' => $heartbeat->pid,
                'last_seen_at' => $heartbeat->lastSeenAt,
                'stopped_at' => null,
            ],
        );
    }

    public function markStopped(WorkerIdentifier $id, DateTimeImmutable $stoppedAt): void
    {
        WorkerHeartbeatModel::query()
            ->where('worker_id', $id->value)
            ->update(['stopped_at' => $stoppedAt]);
    }

    public function find(WorkerIdentifier $id): ?Worker
    {
        $model = WorkerHeartbeatModel::query()
            ->where('worker_id', $id->value)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findAll(): array
    {
        return WorkerHeartbeatModel::query()
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (WorkerHeartbeatModel $model) => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function findPaginated(int $perPage, int $page): array
    {
        return WorkerHeartbeatModel::query()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get()
            ->map(fn (WorkerHeartbeatModel $model) => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function countAll(): int
    {
        return WorkerHeartbeatModel::query()->count();
    }

    public function findSilentSince(DateTimeImmutable $cutoff): array
    {
        return WorkerHeartbeatModel::query()
            ->whereNull('stopped_at')
            ->where('last_seen_at', '<', $cutoff)
            ->orderBy('last_seen_at')
            ->get()
            ->map(fn (WorkerHeartbeatModel $model) => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function countAliveByQueueKey(DateTimeImmutable $aliveSince): array
    {
        $rows = WorkerHeartbeatModel::query()
            ->selectRaw('connection, queue, COUNT(*) as alive_count')
            ->whereNull('stopped_at')
            ->where('last_seen_at', '>=', $aliveSince)
            ->groupBy('connection', 'queue')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row->getAttribute('connection').':'.$row->getAttribute('queue');
            $counts[$key] = (int) $row->getAttribute('alive_count');
        }

        return $counts;
    }

    public function deleteOlderThan(DateTimeImmutable $before): int
    {
        return WorkerHeartbeatModel::query()
            ->where('last_seen_at', '<', $before)
            ->delete();
    }

    private function toDomain(WorkerHeartbeatModel $model): Worker
    {
        return new Worker(
            heartbeat: new WorkerHeartbeat(
                workerId: new WorkerIdentifier($model->worker_id),
                connection: $model->connection,
                queue: $model->queue,
                host: $model->host,
                pid: $model->pid,
                lastSeenAt: $model->last_seen_at,
            ),
            stoppedAt: $model->stopped_at,
        );
    }
}
