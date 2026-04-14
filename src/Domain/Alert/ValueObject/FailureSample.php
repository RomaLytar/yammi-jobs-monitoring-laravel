<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\ValueObject;

use DateTimeImmutable;

/**
 * A single failed job record surfaced inside an AlertPayload so the
 * alert can show ops what actually broke, not just an aggregate count.
 *
 * Pure value carrier — mapping from a JobRecord happens in the evaluator.
 */
final class FailureSample
{
    public function __construct(
        public readonly string $uuid,
        public readonly int $attempt,
        public readonly string $jobClass,
        public readonly ?string $exception,
        public readonly DateTimeImmutable $failedAt,
    ) {}

    public function shortClass(): string
    {
        $parts = explode('\\', $this->jobClass);

        return end($parts) !== false && end($parts) !== '' ? end($parts) : $this->jobClass;
    }

    public function shortException(): ?string
    {
        if ($this->exception === null) {
            return null;
        }

        $firstLine = strtok($this->exception, "\n") ?: $this->exception;

        return mb_strlen($firstLine) > 120 ? mb_substr($firstLine, 0, 117).'...' : $firstLine;
    }
}
