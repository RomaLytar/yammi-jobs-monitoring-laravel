<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Worker;

use Illuminate\Contracts\Cache\Repository;
use Yammi\JobsMonitor\Application\Contract\WorkerAlertStateStore;

/**
 * Cache-backed state store. TTL is long enough that an app restart
 * between ticks keeps the set, short enough that a completely silent
 * monitor eventually self-heals.
 */
final class CacheWorkerAlertStateStore implements WorkerAlertStateStore
{
    private const KEY_PREFIX = 'jobs-monitor:worker-alert-state:';

    private const TTL_SECONDS = 86400;

    public function __construct(private readonly Repository $cache) {}

    public function active(string $category): array
    {
        $raw = $this->cache->get(self::KEY_PREFIX.$category);

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            $raw,
            static fn ($v): bool => is_string($v) && $v !== '',
        ));
    }

    public function replace(string $category, array $keys): void
    {
        $this->cache->put(
            self::KEY_PREFIX.$category,
            array_values(array_unique($keys)),
            self::TTL_SECONDS,
        );
    }
}
