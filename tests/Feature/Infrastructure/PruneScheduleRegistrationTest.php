<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
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

        // On Laravel 10 the scheduler is not a shared singleton outside the
        // console kernel, so registration and assertion would hit different
        // Schedule instances. Pin it so the test observes what was registered
        // (production resolves the same singleton via `schedule:run`).
        $app->singleton(Schedule::class);
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
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        return array_map(
            static fn ($event): string => (string) ($event->description ?? ''),
            $schedule->events(),
        );
    }
}
