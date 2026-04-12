<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Alert\Enum;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;

final class AlertTriggerTest extends TestCase
{
    public function test_string_values_are_stable_for_config(): void
    {
        self::assertSame('failure_rate', AlertTrigger::FailureRate->value);
        self::assertSame('failure_category', AlertTrigger::FailureCategory->value);
        self::assertSame('dlq_size', AlertTrigger::DlqSize->value);
        self::assertSame('job_class_failure_rate', AlertTrigger::JobClassFailureRate->value);
    }

    public function test_requires_trigger_value_is_true_for_category_and_class_triggers(): void
    {
        self::assertFalse(AlertTrigger::FailureRate->requiresTriggerValue());
        self::assertTrue(AlertTrigger::FailureCategory->requiresTriggerValue());
        self::assertFalse(AlertTrigger::DlqSize->requiresTriggerValue());
        self::assertTrue(AlertTrigger::JobClassFailureRate->requiresTriggerValue());
    }

    public function test_requires_window_is_true_except_for_dlq_size(): void
    {
        self::assertTrue(AlertTrigger::FailureRate->requiresWindow());
        self::assertTrue(AlertTrigger::FailureCategory->requiresWindow());
        self::assertFalse(AlertTrigger::DlqSize->requiresWindow());
        self::assertTrue(AlertTrigger::JobClassFailureRate->requiresWindow());
    }

    public function test_label_returns_human_readable_string(): void
    {
        self::assertSame('Failure rate', AlertTrigger::FailureRate->label());
        self::assertSame('Failure category', AlertTrigger::FailureCategory->label());
        self::assertSame('DLQ size', AlertTrigger::DlqSize->label());
        self::assertSame('Job class failure rate', AlertTrigger::JobClassFailureRate->label());
    }
}
