<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Yammi\JobsMonitor\Tests\TestCase;

final class PruneScheduleRegistrationTest extends TestCase
{
    public function test_prune_is_scheduled_by_default(): void
    {
        Artisan::call('schedule:list');

        self::assertStringContainsString('jobs-monitor:prune', Artisan::output());
    }

    /**
     * @define-env disablePruneSchedule
     */
    public function test_prune_is_absent_when_schedule_disabled(): void
    {
        Artisan::call('schedule:list');

        self::assertStringNotContainsString('jobs-monitor:prune', Artisan::output());
    }

    /**
     * @param  Application  $app
     */
    protected function disablePruneSchedule($app): void
    {
        $app['config']->set('jobs-monitor.retention.schedule.enabled', false);
    }
}
