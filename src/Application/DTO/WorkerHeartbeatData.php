<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

use DateTimeImmutable;

/**
 * Cross-layer carrier for a single worker heartbeat pulse.
 *
 * The Infrastructure listener flattens a Laravel queue event into this
 * DTO and hands it to RecordWorkerHeartbeatAction. Application and
 * Domain layers never see Laravel types directly.
 */
final class WorkerHeartbeatData
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $host,
        public readonly int $pid,
        public readonly DateTimeImmutable $observedAt,
    ) {}
}
