<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\Enum;

enum AlertTrigger: string
{
    case FailureRate = 'failure_rate';
    case FailureCategory = 'failure_category';
    case DlqSize = 'dlq_size';
    case JobClassFailureRate = 'job_class_failure_rate';

    public function requiresTriggerValue(): bool
    {
        return match ($this) {
            self::FailureCategory, self::JobClassFailureRate => true,
            self::FailureRate, self::DlqSize => false,
        };
    }

    public function requiresWindow(): bool
    {
        return $this !== self::DlqSize;
    }

    public function label(): string
    {
        return match ($this) {
            self::FailureRate => 'Failure rate',
            self::FailureCategory => 'Failure category',
            self::DlqSize => 'DLQ size',
            self::JobClassFailureRate => 'Job class failure rate',
        };
    }
}
