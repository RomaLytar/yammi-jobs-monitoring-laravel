<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Scheduler\Enum;

enum ScheduledTaskStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Late = 'late';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Success, self::Failed, self::Skipped, self::Late => true,
            self::Running => false,
        };
    }

    public function isFailure(): bool
    {
        return $this === self::Failed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::Late => 'Late',
        };
    }
}
