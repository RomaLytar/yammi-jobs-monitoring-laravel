<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Throttle;

use Illuminate\Contracts\Cache\Repository;
use Yammi\JobsMonitor\Domain\Alert\Contract\AlertThrottle;

/**
 * Cache-backed throttle that relies on atomic add() for deduplication.
 *
 * Concurrent evaluators racing on the same rule will have exactly one
 * of them see add() succeed; the rest see it fail and back off. The
 * host app's configured cache store decides the backing mechanism
 * (array, database, redis, ...).
 */
final class CacheAlertThrottle implements AlertThrottle
{
    private const KEY_PREFIX = 'jobs-monitor:alert-throttle:';

    public function __construct(private readonly Repository $cache) {}

    public function attempt(string $ruleKey, int $cooldownMinutes): bool
    {
        return $this->cache->add(
            $this->cacheKey($ruleKey),
            true,
            $cooldownMinutes * 60,
        );
    }

    private function cacheKey(string $ruleKey): string
    {
        return self::KEY_PREFIX.$ruleKey;
    }
}
