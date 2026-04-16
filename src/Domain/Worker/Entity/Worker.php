<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Worker\Entity;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Enum\WorkerStatus;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;

/**
 * A queue worker observed via Laravel's queue events.
 *
 * Status is derived from the latest heartbeat against a host-provided
 * silent threshold. A worker that sent `WorkerStopping` is Dead
 * regardless of when its last heartbeat was recorded — the process
 * intentionally shut down.
 */
final class Worker
{
    /**
     * Multiplier applied to the silent threshold to decide "dead".
     * Past this point a worker that never sent WorkerStopping is
     * assumed to have crashed rather than paused.
     */
    private const DEAD_MULTIPLIER = 10;

    public function __construct(
        private readonly WorkerHeartbeat $heartbeat,
        private readonly ?DateTimeImmutable $stoppedAt = null,
    ) {}

    public function heartbeat(): WorkerHeartbeat
    {
        return $this->heartbeat;
    }

    public function stoppedAt(): ?DateTimeImmutable
    {
        return $this->stoppedAt;
    }

    public function classifyStatus(DateTimeImmutable $now, int $silentAfterSeconds): WorkerStatus
    {
        if ($this->stoppedAt !== null) {
            return WorkerStatus::Dead;
        }

        $elapsed = $now->getTimestamp() - $this->heartbeat->lastSeenAt->getTimestamp();

        if ($elapsed <= $silentAfterSeconds) {
            return WorkerStatus::Alive;
        }

        if ($elapsed > $silentAfterSeconds * self::DEAD_MULTIPLIER) {
            return WorkerStatus::Dead;
        }

        return WorkerStatus::Silent;
    }
}
