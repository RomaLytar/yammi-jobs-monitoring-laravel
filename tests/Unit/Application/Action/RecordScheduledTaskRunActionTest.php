<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\RecordScheduledTaskRunAction;
use Yammi\JobsMonitor\Application\DTO\ScheduledTaskRunData;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;

final class RecordScheduledTaskRunActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_new_running_record_when_no_existing_found(): void
    {
        $startedAt = new DateTimeImmutable('2026-04-16T10:00:00Z');

        $repo = Mockery::mock(ScheduledTaskRunRepository::class);
        $repo->shouldReceive('findRunning')
            ->with('schedule-mutex-1', $startedAt)
            ->once()
            ->andReturnNull();
        $repo->shouldReceive('save')
            ->once()
            ->withArgs(function (ScheduledTaskRun $run): bool {
                return $run->mutex === 'schedule-mutex-1'
                    && $run->taskName === 'App\\Console\\Commands\\SyncOrders'
                    && $run->expression === '*/5 * * * *'
                    && $run->status() === ScheduledTaskStatus::Running;
            });

        $action = new RecordScheduledTaskRunAction($repo);

        $action(new ScheduledTaskRunData(
            mutex: 'schedule-mutex-1',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            status: ScheduledTaskStatus::Running,
            startedAt: $startedAt,
            host: 'worker-01',
            command: 'php artisan sync:orders',
        ));

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_transitions_existing_running_record_to_success(): void
    {
        $startedAt = new DateTimeImmutable('2026-04-16T10:00:00Z');
        $finishedAt = new DateTimeImmutable('2026-04-16T10:00:05Z');

        $existingRun = new ScheduledTaskRun(
            mutex: 'schedule-mutex-2',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            startedAt: $startedAt,
        );

        $repo = Mockery::mock(ScheduledTaskRunRepository::class);
        $repo->shouldReceive('findRunning')
            ->with('schedule-mutex-2', $startedAt)
            ->once()
            ->andReturn($existingRun);
        $repo->shouldReceive('save')
            ->once()
            ->withArgs(function (ScheduledTaskRun $run) use ($existingRun): bool {
                return $run === $existingRun
                    && $run->status() === ScheduledTaskStatus::Success
                    && $run->exitCode() === 0
                    && $run->output() === 'Synced 42 orders';
            });

        $action = new RecordScheduledTaskRunAction($repo);

        $action(new ScheduledTaskRunData(
            mutex: 'schedule-mutex-2',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            status: ScheduledTaskStatus::Success,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            exitCode: 0,
            output: 'Synced 42 orders',
        ));

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_transitions_existing_running_record_to_failed(): void
    {
        $startedAt = new DateTimeImmutable('2026-04-16T10:00:00Z');
        $finishedAt = new DateTimeImmutable('2026-04-16T10:00:03Z');

        $existingRun = new ScheduledTaskRun(
            mutex: 'schedule-mutex-3',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            startedAt: $startedAt,
        );

        $repo = Mockery::mock(ScheduledTaskRunRepository::class);
        $repo->shouldReceive('findRunning')
            ->with('schedule-mutex-3', $startedAt)
            ->once()
            ->andReturn($existingRun);
        $repo->shouldReceive('save')
            ->once()
            ->withArgs(function (ScheduledTaskRun $run): bool {
                return $run->status() === ScheduledTaskStatus::Failed
                    && $run->exception() === 'Connection refused'
                    && $run->exitCode() === 1;
            });

        $action = new RecordScheduledTaskRunAction($repo);

        $action(new ScheduledTaskRunData(
            mutex: 'schedule-mutex-3',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            status: ScheduledTaskStatus::Failed,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            exitCode: 1,
            exception: 'Connection refused',
        ));

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_transitions_to_skipped(): void
    {
        $startedAt = new DateTimeImmutable('2026-04-16T10:00:00Z');
        $finishedAt = new DateTimeImmutable('2026-04-16T10:00:00Z');

        $existingRun = new ScheduledTaskRun(
            mutex: 'schedule-mutex-4',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            startedAt: $startedAt,
        );

        $repo = Mockery::mock(ScheduledTaskRunRepository::class);
        $repo->shouldReceive('findRunning')
            ->once()
            ->andReturn($existingRun);
        $repo->shouldReceive('save')
            ->once()
            ->withArgs(function (ScheduledTaskRun $run): bool {
                return $run->status() === ScheduledTaskStatus::Skipped
                    && $run->output() === 'Already running';
            });

        $action = new RecordScheduledTaskRunAction($repo);

        $action(new ScheduledTaskRunData(
            mutex: 'schedule-mutex-4',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            status: ScheduledTaskStatus::Skipped,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            output: 'Already running',
        ));

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_falls_back_to_started_at_when_finished_at_is_null(): void
    {
        $startedAt = new DateTimeImmutable('2026-04-16T10:00:00Z');

        $existingRun = new ScheduledTaskRun(
            mutex: 'schedule-mutex-5',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            startedAt: $startedAt,
        );

        $repo = Mockery::mock(ScheduledTaskRunRepository::class);
        $repo->shouldReceive('findRunning')
            ->once()
            ->andReturn($existingRun);
        $repo->shouldReceive('save')
            ->once()
            ->withArgs(function (ScheduledTaskRun $run) use ($startedAt): bool {
                return $run->status() === ScheduledTaskStatus::Success
                    && $run->finishedAt() == $startedAt;
            });

        $action = new RecordScheduledTaskRunAction($repo);

        $action(new ScheduledTaskRunData(
            mutex: 'schedule-mutex-5',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            status: ScheduledTaskStatus::Success,
            startedAt: $startedAt,
            finishedAt: null,
        ));

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_transitions_to_late(): void
    {
        $startedAt = new DateTimeImmutable('2026-04-16T10:00:00Z');
        $detectedAt = new DateTimeImmutable('2026-04-16T10:15:00Z');

        $existingRun = new ScheduledTaskRun(
            mutex: 'schedule-mutex-6',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            startedAt: $startedAt,
        );

        $repo = Mockery::mock(ScheduledTaskRunRepository::class);
        $repo->shouldReceive('findRunning')
            ->once()
            ->andReturn($existingRun);
        $repo->shouldReceive('save')
            ->once()
            ->withArgs(function (ScheduledTaskRun $run): bool {
                return $run->status() === ScheduledTaskStatus::Late;
            });

        $action = new RecordScheduledTaskRunAction($repo);

        $action(new ScheduledTaskRunData(
            mutex: 'schedule-mutex-6',
            taskName: 'App\\Console\\Commands\\SyncOrders',
            expression: '*/5 * * * *',
            timezone: 'UTC',
            status: ScheduledTaskStatus::Late,
            startedAt: $startedAt,
            finishedAt: $detectedAt,
        ));

        Mockery::close();
        self::assertTrue(true);
    }
}
