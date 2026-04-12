<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Alert\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\Exception\InvalidAlertRule;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;

final class AlertRuleTest extends TestCase
{
    public function test_valid_failure_rate_rule_is_constructible(): void
    {
        $rule = new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 10,
            channels: ['slack'],
            cooldownMinutes: 15,
        );

        self::assertSame(AlertTrigger::FailureRate, $rule->trigger);
        self::assertSame('5m', $rule->window);
        self::assertSame(10, $rule->threshold);
        self::assertSame(['slack'], $rule->channels);
        self::assertSame(15, $rule->cooldownMinutes);
        self::assertNull($rule->triggerValue);
    }

    public function test_failure_category_rule_requires_trigger_value(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('requires a "value" field');

        new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: null,
        );
    }

    public function test_failure_category_rule_with_trigger_value_is_valid(): void
    {
        $rule = new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '10m',
            threshold: 1,
            channels: ['slack', 'mail'],
            cooldownMinutes: 5,
            triggerValue: 'critical',
        );

        self::assertSame('critical', $rule->triggerValue);
    }

    public function test_failure_rate_rule_rejects_trigger_value(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('does not accept a "value" field');

        new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: 'something',
        );
    }

    public function test_dlq_size_rule_does_not_require_window(): void
    {
        $rule = new AlertRule(
            trigger: AlertTrigger::DlqSize,
            window: null,
            threshold: 50,
            channels: ['slack'],
            cooldownMinutes: 30,
        );

        self::assertNull($rule->window);
    }

    public function test_windowed_trigger_requires_window(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('requires a "window" field');

        new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: null,
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
        );
    }

    public function test_empty_channels_is_rejected(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('at least one notification channel');

        new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 1,
            channels: [],
            cooldownMinutes: 5,
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonPositiveProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }

    #[DataProvider('nonPositiveProvider')]
    public function test_non_positive_threshold_is_rejected(int $threshold): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('threshold must be a positive integer');

        new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: $threshold,
            channels: ['slack'],
            cooldownMinutes: 5,
        );
    }

    #[DataProvider('nonPositiveProvider')]
    public function test_non_positive_cooldown_is_rejected(int $cooldown): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('cooldown_minutes must be a positive integer');

        new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: $cooldown,
        );
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validWindowProvider(): iterable
    {
        yield '30 seconds' => ['30s', 30];
        yield '5 minutes' => ['5m', 300];
        yield '2 hours' => ['2h', 7200];
        yield '1 day' => ['1d', 86400];
    }

    #[DataProvider('validWindowProvider')]
    public function test_window_is_parsed_to_seconds(string $window, int $expectedSeconds): void
    {
        $rule = new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: $window,
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
        );

        self::assertSame($expectedSeconds, $rule->windowSeconds());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidWindowProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no unit' => ['5'];
        yield 'bad unit' => ['5x'];
        yield 'zero minutes' => ['0m'];
        yield 'negative' => ['-5m'];
        yield 'letters before number' => ['m5'];
    }

    #[DataProvider('invalidWindowProvider')]
    public function test_invalid_window_is_rejected(string $window): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('is not a valid duration');

        new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: $window,
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
        );
    }

    public function test_window_seconds_is_null_for_no_window_trigger(): void
    {
        $rule = new AlertRule(
            trigger: AlertTrigger::DlqSize,
            window: null,
            threshold: 10,
            channels: ['slack'],
            cooldownMinutes: 10,
        );

        self::assertNull($rule->windowSeconds());
    }

    public function test_rule_key_is_deterministic_and_unique_per_configuration(): void
    {
        $ruleA = new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: 'critical',
        );

        $ruleB = new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: 'critical',
        );

        $ruleC = new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '5m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: 'permanent',
        );

        self::assertSame($ruleA->ruleKey(), $ruleB->ruleKey());
        self::assertNotSame($ruleA->ruleKey(), $ruleC->ruleKey());
    }
}
