<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\Enum;

enum AlertTrigger: string
{
    case FailureRate = 'failure_rate';
    case FailureCategory = 'failure_category';
    case DlqSize = 'dlq_size';
    case JobClassFailureRate = 'job_class_failure_rate';
    case FailureGroupNew = 'failure_group_new';
    case FailureGroupBurst = 'failure_group_burst';
    case ScheduledTaskFailed = 'scheduled_task_failed';
    case ScheduledTaskLate = 'scheduled_task_late';
    case DurationAnomaly = 'duration_anomaly';
    case PartialCompletion = 'partial_completion';
    case ZeroProcessed = 'zero_processed';

    public function requiresTriggerValue(): bool
    {
        return match ($this) {
            self::FailureCategory, self::JobClassFailureRate => true,
            self::FailureRate, self::DlqSize, self::FailureGroupNew, self::FailureGroupBurst,
            self::ScheduledTaskFailed, self::ScheduledTaskLate, self::DurationAnomaly,
            self::PartialCompletion, self::ZeroProcessed => false,
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
            self::FailureGroupNew => 'New failure groups',
            self::FailureGroupBurst => 'Failure group burst',
            self::ScheduledTaskFailed => 'Scheduled task failed',
            self::ScheduledTaskLate => 'Scheduled task ran late',
            self::DurationAnomaly => 'Job duration anomaly',
            self::PartialCompletion => 'Partial completion',
            self::ZeroProcessed => 'Silent success (zero processed)',
        };
    }
}
