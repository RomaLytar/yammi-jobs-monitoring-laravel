<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Entity;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
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

    private ?FailureCategory $failureCategory = null;

    /** @var array<string|int, mixed>|null */
    private ?array $payload = null;

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

    public function failureCategory(): ?FailureCategory
    {
        return $this->failureCategory;
    }

    /**
     * @return array<string|int, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param  array<string|int, mixed>  $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function markAsProcessed(DateTimeImmutable $finishedAt): void
    {
        $this->ensureNotTerminal();

        $this->status = JobStatus::Processed;
        $this->finishedAt = $finishedAt;
        $this->duration = Duration::between($this->startedAt, $finishedAt);
    }

    public function markAsFailed(
        DateTimeImmutable $finishedAt,
        string $exception,
        ?FailureCategory $failureCategory = null,
    ): void {
        $this->ensureNotTerminal();

        $this->status = JobStatus::Failed;
        $this->finishedAt = $finishedAt;
        $this->duration = Duration::between($this->startedAt, $finishedAt);
        $this->exception = $exception;
        $this->failureCategory = $failureCategory;
    }

    private function ensureNotTerminal(): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidJobTransition::fromTerminalState($this->id, $this->status);
        }
    }
}
