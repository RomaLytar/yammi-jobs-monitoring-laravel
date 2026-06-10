<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Yammi\JobsMonitor\Tests\TestCase;

final class PruneScheduleRegistrationTest extends TestCase
{
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
     * @param  \Illuminate\Foundation\Application  $app
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
        /** @var Application $app */
        $app = $this->app;
        $app->boot();

        /** @var Schedule $schedule */
        $schedule = $app->make(Schedule::class);

        return array_values(array_map(
            static fn ($event): string => (string) ($event->description ?? ''),
            $schedule->events(),
        ));
    }
}
