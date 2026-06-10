<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Tests\TestCase;

final class PruneScheduleRegistrationTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Construct the console kernel before the app boots so its schedule
        // definition runs before the package registers its schedules — the
        // ordering a real app has. Without it, Laravel 10 re-binds the
        // Schedule singleton after the package and orphans its registrations
        // in the test harness (the schedules still work in production).
        $app->make(ConsoleKernel::class);
    }

    public function test_prune_is_scheduled_by_default(): void
    {
        self::assertContains('jobs-monitor:prune', $this->scheduledNames());
    }

    /**
     * @define-env disablePruneSchedule
     */
    public function test_prune_is_absent_when_schedule_disabled(): void
    {
        self::assertNotContains('jobs-monitor:prune', $this->scheduledNames());
    }

    /**
     * @param  Application  $app
     */
    protected function disablePruneSchedule($app): void
    {
        $app['config']->set('jobs-monitor.retention.schedule.enabled', false);
    }

    /**
     * @return list<string>
     */
    private function scheduledNames(): array
    {
        $this->app->boot();

        return array_values(array_map(
            static fn ($event): string => (string) ($event->description ?? ''),
            $this->app->make(Schedule::class)->events(),
        ));
    }
}
