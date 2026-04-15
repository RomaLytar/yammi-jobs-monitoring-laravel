<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;

final class ScheduledTaskRunData
{
    public function __construct(
        public readonly string $mutex,
        public readonly string $taskName,
        public readonly string $expression,
        public readonly ?string $timezone,
        public readonly ScheduledTaskStatus $status,
        public readonly DateTimeImmutable $startedAt,
        public readonly ?DateTimeImmutable $finishedAt = null,
        public readonly ?int $exitCode = null,
        public readonly ?string $output = null,
        public readonly ?string $exception = null,
        public readonly ?string $host = null,
    ) {}
}
