<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Worker;

use Illuminate\Contracts\Cache\Repository;
use Yammi\JobsMonitor\Application\Contract\HeartbeatRateLimiter;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

/**
 * Cache-backed rate limiter using atomic add(). Concurrent events for
 * the same worker race on add() — exactly one wins per interval.
 */
final class CacheHeartbeatRateLimiter implements HeartbeatRateLimiter
{
    private const KEY_PREFIX = 'jobs-monitor:worker-heartbeat:';

    public function __construct(private readonly Repository $cache) {}

    public function attempt(WorkerIdentifier $id, int $intervalSeconds): bool
    {
        return $this->cache->add(
            self::KEY_PREFIX.$id->value,
            true,
            $intervalSeconds,
        );
    }
}
