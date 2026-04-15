<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Listener;

use DateTimeImmutable;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as SchedulerEvent;
use Throwable;
use Yammi\JobsMonitor\Application\Action\RecordScheduledTaskRunAction;
use Yammi\JobsMonitor\Application\DTO\ScheduledTaskRunData;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;

/**
 * Bridge from Laravel scheduler events to the Application layer.
 *
 * A task run is correlated by its mutex name + startedAt. The starting
 * event creates the record in Running state; the corresponding
 * finished/failed/skipped event flips it to its terminal state.
 *
 * The subscriber is intentionally resilient — any exception is swallowed
 * so a monitor failure cannot break the host app's scheduler.
 */
final class SchedulerSubscriber
{
    /**
     * In-memory correlation between mutex and the DateTimeImmutable captured
     * at ScheduledTaskStarting time. The scheduler fires Starting + Finished
     * in the same PHP process, so a simple array is sufficient.
     *
     * @var array<string, DateTimeImmutable>
     */
    private array $startedAtByMutex = [];

    public function __construct(
        private readonly RecordScheduledTaskRunAction $action,
        private readonly int $outputMaxLength = 4096,
    ) {}

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $this->safely(function () use ($event): void {
            $task = $event->task;
            $mutex = $this->mutex($task);
            $startedAt = new DateTimeImmutable;

            $this->startedAtByMutex[$mutex] = $startedAt;

            ($this->action)(new ScheduledTaskRunData(
                mutex: $mutex,
                taskName: $this->describe($task),
                expression: $task->expression,
                timezone: $this->timezone($task),
                status: ScheduledTaskStatus::Running,
                startedAt: $startedAt,
                host: gethostname() ?: null,
                command: $this->command($task),
            ));
        });
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $this->safely(function () use ($event): void {
            $task = $event->task;
            $mutex = $this->mutex($task);
            $startedAt = $this->consumeStartedAt($mutex);
            $finishedAt = new DateTimeImmutable;

            ($this->action)(new ScheduledTaskRunData(
                mutex: $mutex,
                taskName: $this->describe($task),
                expression: $task->expression,
                timezone: $this->timezone($task),
                status: ScheduledTaskStatus::Success,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                output: $this->captureOutput($task),
                command: $this->command($task),
            ));
        });
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $this->safely(function () use ($event): void {
            $task = $event->task;
            $mutex = $this->mutex($task);
            $startedAt = $this->consumeStartedAt($mutex);
            $finishedAt = new DateTimeImmutable;

            ($this->action)(new ScheduledTaskRunData(
                mutex: $mutex,
                taskName: $this->describe($task),
                expression: $task->expression,
                timezone: $this->timezone($task),
                status: ScheduledTaskStatus::Failed,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                exception: $event->exception instanceof Throwable
                    ? sprintf('%s: %s', $event->exception::class, $event->exception->getMessage())
                    : null,
                output: $this->captureOutput($task),
                command: $this->command($task),
            ));
        });
    }

    public function handleSkipped(ScheduledTaskSkipped $event): void
    {
        $this->safely(function () use ($event): void {
            $task = $event->task;
            $mutex = $this->mutex($task);
            $startedAt = new DateTimeImmutable;
            $finishedAt = $startedAt;

            ($this->action)(new ScheduledTaskRunData(
                mutex: $mutex,
                taskName: $this->describe($task),
                expression: $task->expression,
                timezone: $this->timezone($task),
                status: ScheduledTaskStatus::Skipped,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                command: $this->command($task),
            ));
        });
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ScheduledTaskStarting::class => 'handleStarting',
            ScheduledTaskFinished::class => 'handleFinished',
            ScheduledTaskFailed::class => 'handleFailed',
            ScheduledTaskSkipped::class => 'handleSkipped',
        ];
    }

    private function mutex(SchedulerEvent $task): string
    {
        return method_exists($task, 'mutexName') ? $task->mutexName() : sha1((string) $task->command.$task->expression);
    }

    private function command(SchedulerEvent $task): ?string
    {
        $cmd = $task->command ?? null;

        return is_string($cmd) && $cmd !== '' ? $cmd : null;
    }

    private function describe(SchedulerEvent $task): string
    {
        $summary = $task->description ?? null;
        if (is_string($summary) && $summary !== '') {
            return $summary;
        }

        return (string) ($task->command ?? 'closure');
    }

    private function timezone(SchedulerEvent $task): ?string
    {
        if (! property_exists($task, 'timezone')) {
            return null;
        }

        $tz = $task->timezone;

        return $tz === null ? null : (string) $tz;
    }

    private function consumeStartedAt(string $mutex): DateTimeImmutable
    {
        $startedAt = $this->startedAtByMutex[$mutex] ?? new DateTimeImmutable;
        unset($this->startedAtByMutex[$mutex]);

        return $startedAt;
    }

    private function captureOutput(SchedulerEvent $task): ?string
    {
        $path = property_exists($task, 'output') ? $task->output : null;
        if (! is_string($path) || $path === '' || $path === '/dev/null' || ! is_readable($path)) {
            return null;
        }

        $content = @file_get_contents($path, false, null, 0, $this->outputMaxLength + 1);
        if ($content === false || $content === '') {
            return null;
        }

        return strlen($content) > $this->outputMaxLength
            ? substr($content, 0, $this->outputMaxLength).'…'
            : $content;
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            // Monitor failures must never break the host scheduler.
        }
    }
}
