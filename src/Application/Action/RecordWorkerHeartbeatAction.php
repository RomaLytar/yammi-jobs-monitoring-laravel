<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\Contract\HeartbeatRateLimiter;
use Yammi\JobsMonitor\Application\DTO\WorkerHeartbeatData;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

/**
 * Record a single worker heartbeat. Idempotent per worker, upserted on
 * the worker identifier. Rate-limited to one accepted write per worker
 * per `intervalSeconds` to avoid DB write storms on high-poll workers.
 */
final class RecordWorkerHeartbeatAction
{
    public function __construct(
        private readonly WorkerRepository $repository,
        private readonly HeartbeatRateLimiter $rateLimiter,
        private readonly int $intervalSeconds,
    ) {}

    public function __invoke(WorkerHeartbeatData $data): void
    {
        $workerId = new WorkerIdentifier($data->workerId);

        if (! $this->rateLimiter->attempt($workerId, $this->intervalSeconds)) {
            return;
        }

        $this->repository->recordHeartbeat(new WorkerHeartbeat(
            workerId: $workerId,
            connection: $data->connection,
            queue: $data->queue,
            host: $data->host,
            pid: $data->pid,
            lastSeenAt: $data->observedAt,
        ));
    }
}
