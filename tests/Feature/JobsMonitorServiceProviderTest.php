<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature;

use Yammi\JobsMonitor\Tests\TestCase;

final class JobsMonitorServiceProviderTest extends TestCase
{
    public function test_it_merges_default_config_when_booted(): void
    {
        self::assertTrue(config('jobs-monitor.enabled'));
    }
}
