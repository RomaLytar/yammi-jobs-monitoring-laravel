<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature;

use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Tests\TestCase;

final class JobsMonitorServiceProviderTest extends TestCase
{
    public function test_it_merges_default_config_when_booted(): void
    {
        self::assertTrue(config('jobs-monitor.enabled'));
    }

    public function test_it_binds_null_metrics_driver_as_default(): void
    {
        $driver = $this->app->make(QueueMetricsDriver::class);

        self::assertInstanceOf(NullMetricsDriver::class, $driver);
    }
}
