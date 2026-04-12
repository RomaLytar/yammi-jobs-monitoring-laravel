<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Enum;

enum FailureCategory: string
{
    case Transient = 'transient';
    case Permanent = 'permanent';
    case Critical = 'critical';
    case Unknown = 'unknown';

    public function isRetryable(): bool
    {
        return $this === self::Transient;
    }

    public function label(): string
    {
        return match ($this) {
            self::Transient => 'Transient',
            self::Permanent => 'Permanent',
            self::Critical => 'Critical',
            self::Unknown => 'Unknown',
        };
    }
}
