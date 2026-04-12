<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Entity;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Exception\InvalidJobTransition;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Duration;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;

final class JobRecord
{
    private JobStatus $status;

    private ?DateTimeImmutable $finishedAt = null;

    private ?Duration $duration = null;

    private ?string $exception = null;

    public function __construct(
        public readonly JobIdentifier $id,
        public readonly Attempt $attempt,
        public readonly string $jobClass,
        public readonly string $connection,
        public readonly QueueName $queue,
        public readonly DateTimeImmutable $startedAt,
    ) {
        $this->status = JobStatus::Processing;
    }

    public function status(): JobStatus
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

    public function exception(): ?string
    {
        return $this->exception;
    }

    public function markAsProcessed(DateTimeImmutable $finishedAt): void
    {
        $this->ensureNotTerminal();

        $this->status = JobStatus::Processed;
        $this->finishedAt = $finishedAt;
        $this->duration = Duration::between($this->startedAt, $finishedAt);
    }

    public function markAsFailed(DateTimeImmutable $finishedAt, string $exception): void
    {
        $this->ensureNotTerminal();

        $this->status = JobStatus::Failed;
        $this->finishedAt = $finishedAt;
        $this->duration = Duration::between($this->startedAt, $finishedAt);
        $this->exception = $exception;
    }

    private function ensureNotTerminal(): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidJobTransition::fromTerminalState($this->id, $this->status);
        }
    }
}
