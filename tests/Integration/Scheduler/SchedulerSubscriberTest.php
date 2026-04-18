<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Scheduler;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as SchedulerEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class SchedulerSubscriberTest extends TestCase
{
    public function test_successful_run_is_recorded_from_starting_to_finished(): void
    {
        $task = $this->fakeTask('telescope:prune', '0 3 * * *');

        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->dispatch(new ScheduledTaskStarting($task));
        $dispatcher->dispatch(new ScheduledTaskFinished($task, 12.3));

        $row = DB::table('jobs_monitor_scheduled_runs')->first();

        self::assertNotNull($row);
        self::assertSame(ScheduledTaskStatus::Success->value, $row->status);
        self::assertSame('0 3 * * *', $row->expression);
        self::assertSame('telescope:prune', $row->task_name);
        self::assertNotNull($row->finished_at);
        self::assertNotNull($row->duration_ms);
    }

    public function test_failed_run_stores_exception(): void
    {
        $task = $this->fakeTask('artisan import:customers', '*/5 * * * *');

        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->dispatch(new ScheduledTaskStarting($task));
        $dispatcher->dispatch(new ScheduledTaskFailed($task, new RuntimeException('CSV source unavailable')));

        $row = DB::table('jobs_monitor_scheduled_runs')->first();

        self::assertNotNull($row);
        self::assertSame(ScheduledTaskStatus::Failed->value, $row->status);
        self::assertStringContainsString('CSV source unavailable', $row->exception);
    }

    public function test_skipped_run_is_recorded_in_skipped_state(): void
    {
        $task = $this->fakeTask('artisan sync:external', '0 * * * *');

        $this->app->make(Dispatcher::class)->dispatch(new ScheduledTaskSkipped($task));

        $row = DB::table('jobs_monitor_scheduled_runs')->first();

        self::assertNotNull($row);
        self::assertSame(ScheduledTaskStatus::Skipped->value, $row->status);
    }

    public function test_monitor_failure_does_not_break_host_scheduler(): void
    {
        // Fake event with a mutex that will produce the same record on
        // subsequent calls — idempotent upsert means no exception leaks.
        $task = $this->fakeTask('artisan ok', '* * * * *');

        $dispatcher = $this->app->make(Dispatcher::class);

        // Doubly-firing the finished event (no matching starting) is a realistic
        // race on multi-host setups. It must not throw.
        $dispatcher->dispatch(new ScheduledTaskFinished($task, 1.0));
        $dispatcher->dispatch(new ScheduledTaskFinished($task, 1.0));

        self::assertTrue(true);
    }

    public function test_all_package_index_names_on_scheduled_runs_fit_portable_limit(): void
    {
        $indexes = DB::select(
            "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'jobs_monitor_scheduled_runs'"
        );

        foreach ($indexes as $index) {
            if (str_starts_with($index->name, 'sqlite_autoindex_')) {
                continue;
            }
            self::assertLessThanOrEqual(63, strlen($index->name), sprintf(
                'Index "%s" is %d characters (> 63 portable limit).',
                $index->name,
                strlen($index->name),
            ));
        }
    }

    public function test_repository_reports_counts(): void
    {
        $repo = $this->app->make(ScheduledTaskRunRepository::class);

        $taskFailed = $this->fakeTask('artisan flaky', '* * * * *');
        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->dispatch(new ScheduledTaskStarting($taskFailed));
        $dispatcher->dispatch(new ScheduledTaskFailed($taskFailed, new RuntimeException('boom')));

        self::assertSame(1, $repo->countFailedSince(new \DateTimeImmutable('-1 hour')));
        self::assertSame(0, $repo->countLateSince(new \DateTimeImmutable('-1 hour')));
    }

    private function fakeTask(string $command, string $expression): SchedulerEvent
    {
        return new class($command, $expression) extends SchedulerEvent
        {
            public function __construct(string $command, string $expression)
            {
                $this->command = $command;
                $this->expression = $expression;
                $this->description = $command;
                $this->timezone = null;
                $this->output = '/dev/null';
            }

            public function mutexName(): string
            {
                return 'framework/schedule-'.sha1($this->expression.$this->command);
            }
        };
    }
}
