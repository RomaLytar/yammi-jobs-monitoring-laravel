<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Alert\Throttle;

use Illuminate\Contracts\Cache\Repository;
use Yammi\JobsMonitor\Infrastructure\Alert\Throttle\CacheAlertThrottle;
use Yammi\JobsMonitor\Tests\TestCase;

final class CacheAlertThrottleTest extends TestCase
{
    public function test_first_attempt_is_allowed(): void
    {
        $throttle = new CacheAlertThrottle($this->cache());

        self::assertTrue($throttle->attempt('rule-a', 5));
    }

    public function test_second_attempt_within_cooldown_is_blocked(): void
    {
        $throttle = new CacheAlertThrottle($this->cache());

        $throttle->attempt('rule-a', 5);

        self::assertFalse($throttle->attempt('rule-a', 5));
    }

    public function test_different_rule_keys_do_not_interfere(): void
    {
        $throttle = new CacheAlertThrottle($this->cache());

        self::assertTrue($throttle->attempt('rule-a', 5));
        self::assertTrue($throttle->attempt('rule-b', 5));
    }

    public function test_cooldown_window_expires_allowing_a_new_attempt(): void
    {
        $cache = $this->cache();
        $throttle = new CacheAlertThrottle($cache);

        $throttle->attempt('rule-a', 5);

        // Simulate cooldown expiry by wiping the cache directly
        $cache->clear();

        self::assertTrue($throttle->attempt('rule-a', 5));
    }

    public function test_cache_key_is_scoped_to_avoid_collisions(): void
    {
        $cache = $this->cache();
        $throttle = new CacheAlertThrottle($cache);

        $throttle->attempt('rule-a', 5);

        // The throttle must not write under the raw rule key — that would
        // collide with any host-app cache usage. Verify our namespaced
        // key is present and the raw one is untouched.
        self::assertFalse($cache->has('rule-a'));
        self::assertTrue($cache->has('jobs-monitor:alert-throttle:rule-a'));
    }

    private function cache(): Repository
    {
        return $this->app->make('cache')->store('array');
    }
}
