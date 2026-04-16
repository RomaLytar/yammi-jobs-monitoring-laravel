<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

/**
 * Throttles how often a single worker's heartbeat is written.
 *
 * Every `Looping` / `JobProcessing` event would otherwise produce a
 * write on every poll — burning through the DB for no additional
 * signal. The limiter guarantees at most one accepted pulse per
 * worker per interval.
 */
interface HeartbeatRateLimiter
{
    /**
     * Attempt to acquire a slot for the given worker. Returns true if
     * the caller should proceed with the write, false if a previous
     * pulse within the interval has already been accepted.
     */
    public function attempt(WorkerIdentifier $id, int $intervalSeconds): bool;
}
