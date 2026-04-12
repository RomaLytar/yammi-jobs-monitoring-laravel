<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Facade;

use Yammi\JobsMonitor\Application\DTO\JobClassStatsData;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Infrastructure\Facade\JobsMonitor;
use Yammi\JobsMonitor\Tests\TestCase;

final class JobsMonitorFacadeTest extends TestCase
{
    public function test_facade_resolves_to_service(): void
    {
        self::assertInstanceOf(
            JobsMonitorService::class,
            JobsMonitor::getFacadeRoot(),
        );
    }

    public function test_queue_size_returns_null_with_null_driver(): void
    {
        self::assertNull(JobsMonitor::queueSize('default'));
    }

    public function test_recent_jobs_returns_array(): void
    {
        self::assertIsArray(JobsMonitor::recentJobs(10));
    }

    public function test_stats_returns_dto(): void
    {
        $stats = JobsMonitor::stats('App\\Jobs\\NonExistent');

        self::assertInstanceOf(JobClassStatsData::class, $stats);
        self::assertSame(0, $stats->total);
    }
}
