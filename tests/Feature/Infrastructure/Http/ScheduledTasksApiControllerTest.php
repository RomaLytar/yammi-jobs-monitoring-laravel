<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Tests\TestCase;

final class ScheduledTasksApiControllerTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function enableApi($app): void
    {
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    /**
     * @define-env enableApi
     */
    public function test_index_returns_paginated_runs(): void
    {
        $this->seedRun('mutex-1', 'artisan a:b', ScheduledTaskStatus::Success);
        $this->seedRun('mutex-2', 'artisan c:d', ScheduledTaskStatus::Failed, exception: 'RuntimeException: boom');

        $response = $this->getJson('/api/jobs-monitor/scheduled?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'mutex', 'task_name', 'command', 'expression', 'status', 'started_at', 'finished_at', 'duration_ms']],
            'meta' => ['total', 'page', 'per_page', 'last_page', 'sort', 'dir'],
        ]);
        self::assertSame(2, $response->json('meta.total'));
    }

    /**
     * @define-env enableApi
     */
    public function test_index_filters_by_status(): void
    {
        $this->seedRun('mutex-ok', 'artisan ok', ScheduledTaskStatus::Success);
        $this->seedRun('mutex-fail', 'artisan fail', ScheduledTaskStatus::Failed, exception: 'RuntimeException: x');

        $response = $this->getJson('/api/jobs-monitor/scheduled?status=failed');

        $response->assertOk();
        self::assertSame(1, $response->json('meta.total'));
        self::assertSame('failed', $response->json('data.0.status'));
    }

    /**
     * @define-env enableApi
     */
    public function test_index_searches_by_task_name(): void
    {
        $this->seedRun('a', 'artisan import:customers', ScheduledTaskStatus::Success);
        $this->seedRun('b', 'artisan billing:run', ScheduledTaskStatus::Success);

        $response = $this->getJson('/api/jobs-monitor/scheduled?search=billing');

        $response->assertOk();
        self::assertSame(1, $response->json('meta.total'));
        self::assertStringContainsString('billing', $response->json('data.0.task_name'));
    }

    /**
     * @define-env enableApi
     */
    public function test_status_counts_returns_each_status_value(): void
    {
        $this->seedRun('a', 'artisan a', ScheduledTaskStatus::Success);
        $this->seedRun('b', 'artisan b', ScheduledTaskStatus::Failed, exception: 'RuntimeException: x');
        $this->seedRun('c', 'artisan c', ScheduledTaskStatus::Late);

        $response = $this->getJson('/api/jobs-monitor/scheduled/status-counts');

        $response->assertOk();
        $counts = $response->json('counts');
        self::assertSame(1, $counts['success']);
        self::assertSame(1, $counts['failed']);
        self::assertSame(1, $counts['late']);
        self::assertSame(0, $counts['running']);
        self::assertSame(0, $counts['skipped']);
    }

    /**
     * @define-env enableApi
     */
    public function test_retry_runs_artisan_command_and_records_new_run(): void
    {
        $id = $this->seedRun('mutex-cache', 'artisan cache:clear', ScheduledTaskStatus::Failed, command: 'cache:clear');

        $response = $this->postJson("/api/jobs-monitor/scheduled/{$id}/retry");

        $response->assertOk();
        $response->assertJsonStructure(['command', 'exit_code', 'succeeded', 'output', 'started_at', 'finished_at']);
        self::assertTrue($response->json('succeeded'));
        // A new "manual retry" run should be recorded for this mutex.
        self::assertTrue(
            DB::table('jobs_monitor_scheduled_runs')
                ->where('mutex', 'mutex-cache')
                ->where('task_name', 'like', '%manual retry%')
                ->exists()
        );
    }

    /**
     * @define-env enableApi
     */
    public function test_retry_returns_422_for_non_artisan_run(): void
    {
        $id = $this->seedRun('mutex-bash', '/usr/bin/php some-script.php', ScheduledTaskStatus::Failed, command: '/usr/bin/php some-script.php');

        $response = $this->postJson("/api/jobs-monitor/scheduled/{$id}/retry");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'This run is not an artisan command, cannot re-run from here.']);
    }

    /**
     * @define-env enableApi
     */
    public function test_retry_returns_404_for_missing_run(): void
    {
        $response = $this->postJson('/api/jobs-monitor/scheduled/999999/retry');

        $response->assertStatus(404);
    }

    private function seedRun(
        string $mutex,
        string $taskName,
        ScheduledTaskStatus $status,
        ?string $exception = null,
        ?string $command = null,
    ): int {
        $now = (new DateTimeImmutable)->format('Y-m-d H:i:s.u');

        return (int) DB::table('jobs_monitor_scheduled_runs')->insertGetId([
            'mutex' => $mutex,
            'task_name' => $taskName,
            'command' => $command,
            'expression' => '* * * * *',
            'timezone' => null,
            'status' => $status->value,
            'started_at' => $now,
            'finished_at' => $status === ScheduledTaskStatus::Running ? null : $now,
            'duration_ms' => $status === ScheduledTaskStatus::Running ? null : 100,
            'exit_code' => $status === ScheduledTaskStatus::Success ? 0 : null,
            'output' => null,
            'exception' => $exception,
            'host' => 'sandbox',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
