<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Alert\Exception;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Alert\Exception\InvalidAlertRule;
use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidAlertRuleTest extends TestCase
{
    public function test_extends_domain_exception(): void
    {
        self::assertInstanceOf(DomainException::class, InvalidAlertRule::emptyChannels());
    }

    public function test_empty_channels_message(): void
    {
        $e = InvalidAlertRule::emptyChannels();

        self::assertSame('Alert rule must have at least one notification channel.', $e->getMessage());
    }

    public function test_non_positive_threshold_message(): void
    {
        $e = InvalidAlertRule::nonPositiveThreshold(-1);

        self::assertSame('Alert rule threshold must be a positive integer, got -1.', $e->getMessage());
    }

    public function test_non_positive_cooldown_message(): void
    {
        $e = InvalidAlertRule::nonPositiveCooldown(0);

        self::assertSame('Alert rule cooldown_minutes must be a positive integer, got 0.', $e->getMessage());
    }

    public function test_invalid_window_message(): void
    {
        $e = InvalidAlertRule::invalidWindow('bogus');

        self::assertSame('Alert rule window "bogus" is not a valid duration. Expected format like "5m", "1h", "2d".', $e->getMessage());
    }

    public function test_missing_trigger_value_message(): void
    {
        $e = InvalidAlertRule::missingTriggerValue('failure_category');

        self::assertSame('Alert rule trigger "failure_category" requires a "value" field.', $e->getMessage());
    }

    public function test_unexpected_trigger_value_message(): void
    {
        $e = InvalidAlertRule::unexpectedTriggerValue('failure_rate');

        self::assertSame('Alert rule trigger "failure_rate" does not accept a "value" field.', $e->getMessage());
    }

    public function test_missing_window_message(): void
    {
        $e = InvalidAlertRule::missingWindow('failure_rate');

        self::assertSame('Alert rule trigger "failure_rate" requires a "window" field.', $e->getMessage());
    }
}
