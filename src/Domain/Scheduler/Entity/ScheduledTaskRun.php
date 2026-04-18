<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Scheduler\Entity;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Duration;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Exception\InvalidScheduledTaskTransition;

final class ScheduledTaskRun
{
    private ScheduledTaskStatus $status;

    private ?DateTimeImmutable $finishedAt = null;

    private ?Duration $duration = null;

    private ?int $exitCode = null;

    private ?string $output = null;

    private ?string $exception = null;

    public function __construct(
        public readonly string $mutex,
        public readonly string $taskName,
        public readonly string $expression,
        public readonly ?string $timezone,
        public readonly DateTimeImmutable $startedAt,
        public readonly ?string $host = null,
        public readonly ?string $command = null,
    ) {
        $this->status = ScheduledTaskStatus::Running;
    }

    public function status(): ScheduledTaskStatus
    {
        return $this->status;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function duration(): ?Duration
    {
        return $this->duration;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function output(): ?string
    {
        return $this->output;
    }

    public function exception(): ?string
    {
        return $this->exception;
    }

    public function markAsSucceeded(
        DateTimeImmutable $finishedAt,
        ?int $exitCode = null,
        ?string $output = null,
    ): void {
        $this->ensureNotTerminal();

        $this->status = ScheduledTaskStatus::Success;
        $this->finishedAt = $finishedAt;
        $this->duration = Duration::between($this->startedAt, $finishedAt);
        $this->exitCode = $exitCode;
        $this->output = $output;
    }

    public function markAsFailed(
        DateTimeImmutable $finishedAt,
        ?string $exception = null,
        ?int $exitCode = null,
        ?string $output = null,
    ): void {
        $this->ensureNotTerminal();

        $this->status = ScheduledTaskStatus::Failed;
        $this->finishedAt = $finishedAt;
        $this->duration = Duration::between($this->startedAt, $finishedAt);
        $this->exception = $exception;
        $this->exitCode = $exitCode;
        $this->output = $output;
    }

    public function markAsSkipped(DateTimeImmutable $finishedAt, ?string $reason = null): void
    {
        $this->ensureNotTerminal();

        $this->status = ScheduledTaskStatus::Skipped;
        $this->finishedAt = $finishedAt;
        $this->duration = Duration::between($this->startedAt, $finishedAt);
        $this->output = $reason;
    }

    /**
     * Transitions a never-finished run into the Late state. Used by the
     * watchdog when a run stayed in Running past its expected deadline —
     * typical cause is a worker/host crash that prevented the Finished event.
     */
    public function markAsLate(DateTimeImmutable $detectedAt): void
    {
        if ($this->status !== ScheduledTaskStatus::Running) {
            throw InvalidScheduledTaskTransition::fromTerminalState($this->mutex, $this->status);
        }

        $this->status = ScheduledTaskStatus::Late;
        $this->finishedAt = $detectedAt;
        $this->duration = Duration::between($this->startedAt, $detectedAt);
    }

    private function ensureNotTerminal(): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidScheduledTaskTransition::fromTerminalState($this->mutex, $this->status);
        }
    }
}
