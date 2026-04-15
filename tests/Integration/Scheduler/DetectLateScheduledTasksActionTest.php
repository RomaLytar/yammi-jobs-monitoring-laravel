<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Scheduler;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Application\Action\DetectLateScheduledTasksAction;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Tests\TestCase;

final class DetectLateScheduledTasksActionTest extends TestCase
{
    public function test_it_flags_stuck_running_records_as_late(): void
    {
        DB::table('jobs_monitor_scheduled_runs')->insert([
            [
                'mutex' => 'stuck-1',
                'task_name' => 'stuck one',
                'expression' => '* * * * *',
                'status' => ScheduledTaskStatus::Running->value,
                'started_at' => (new DateTimeImmutable('2026-04-15 09:00:00'))->format('Y-m-d H:i:s.u'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mutex' => 'recent-2',
                'task_name' => 'still running',
                'expression' => '* * * * *',
                'status' => ScheduledTaskStatus::Running->value,
                'started_at' => now()->format('Y-m-d H:i:s.u'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $action = $this->app->make(DetectLateScheduledTasksAction::class);
        $flagged = $action(new DateTimeImmutable, toleranceMinutes: 30);

        self::assertSame(1, $flagged);
        self::assertSame(ScheduledTaskStatus::Late->value, DB::table('jobs_monitor_scheduled_runs')->where('mutex', 'stuck-1')->value('status'));
        self::assertSame(ScheduledTaskStatus::Running->value, DB::table('jobs_monitor_scheduled_runs')->where('mutex', 'recent-2')->value('status'));
    }
}
