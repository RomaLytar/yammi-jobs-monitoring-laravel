<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Scheduler\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Exception\InvalidScheduledTaskTransition;

final class ScheduledTaskRunTest extends TestCase
{
    public function test_new_run_starts_in_running_state(): void
    {
        $run = $this->makeRun();

        self::assertSame(ScheduledTaskStatus::Running, $run->status());
        self::assertNull($run->finishedAt());
        self::assertNull($run->duration());
    }

    public function test_successful_transition_records_duration(): void
    {
        $run = $this->makeRun(new DateTimeImmutable('2026-04-15 10:00:00'));
        $run->markAsSucceeded(new DateTimeImmutable('2026-04-15 10:00:05'), exitCode: 0, output: 'done');

        self::assertSame(ScheduledTaskStatus::Success, $run->status());
        self::assertSame(5000, $run->duration()?->milliseconds);
        self::assertSame(0, $run->exitCode());
        self::assertSame('done', $run->output());
    }

    public function test_failed_transition_captures_exception(): void
    {
        $run = $this->makeRun();
        $run->markAsFailed(new DateTimeImmutable, 'RuntimeException: boom');

        self::assertSame(ScheduledTaskStatus::Failed, $run->status());
        self::assertSame('RuntimeException: boom', $run->exception());
    }

    public function test_late_transition_only_valid_from_running(): void
    {
        $run = $this->makeRun();
        $run->markAsSucceeded(new DateTimeImmutable);

        $this->expectException(InvalidScheduledTaskTransition::class);
        $run->markAsLate(new DateTimeImmutable);
    }

    public function test_running_to_late_is_allowed(): void
    {
        $run = $this->makeRun(new DateTimeImmutable('2026-04-15 10:00:00'));
        $run->markAsLate(new DateTimeImmutable('2026-04-15 11:00:00'));

        self::assertSame(ScheduledTaskStatus::Late, $run->status());
        self::assertSame(3_600_000, $run->duration()?->milliseconds);
    }

    public function test_cannot_transition_twice(): void
    {
        $run = $this->makeRun();
        $run->markAsSucceeded(new DateTimeImmutable);

        $this->expectException(InvalidScheduledTaskTransition::class);
        $run->markAsFailed(new DateTimeImmutable, 'too late');
    }

    private function makeRun(?DateTimeImmutable $startedAt = null): ScheduledTaskRun
    {
        return new ScheduledTaskRun(
            mutex: 'framework/schedule-abc',
            taskName: 'artisan telescope:prune',
            expression: '0 3 * * *',
            timezone: null,
            startedAt: $startedAt ?? new DateTimeImmutable,
        );
    }
}
