<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\Exception\InvalidAlertRule;

final class AlertRuleFactoryTest extends TestCase
{
    public function test_parses_failure_rate_rule(): void
    {
        $factory = new AlertRuleFactory;

        $rule = $factory->fromArray([
            'trigger' => 'failure_rate',
            'window' => '5m',
            'threshold' => 10,
            'channels' => ['slack'],
            'cooldown_minutes' => 15,
        ]);

        self::assertSame(AlertTrigger::FailureRate, $rule->trigger);
        self::assertSame('5m', $rule->window);
        self::assertSame(10, $rule->threshold);
        self::assertSame(['slack'], $rule->channels);
        self::assertSame(15, $rule->cooldownMinutes);
        self::assertNull($rule->triggerValue);
    }

    public function test_parses_failure_category_rule_with_value(): void
    {
        $factory = new AlertRuleFactory;

        $rule = $factory->fromArray([
            'trigger' => 'failure_category',
            'value' => 'critical',
            'window' => '10m',
            'threshold' => 1,
            'channels' => ['slack', 'mail'],
            'cooldown_minutes' => 5,
        ]);

        self::assertSame('critical', $rule->triggerValue);
    }

    public function test_parses_dlq_size_without_window(): void
    {
        $factory = new AlertRuleFactory;

        $rule = $factory->fromArray([
            'trigger' => 'dlq_size',
            'threshold' => 50,
            'channels' => ['slack'],
            'cooldown_minutes' => 30,
        ]);

        self::assertNull($rule->window);
        self::assertSame(50, $rule->threshold);
    }

    public function test_parses_list_of_rules(): void
    {
        $factory = new AlertRuleFactory;

        $rules = $factory->fromList([
            [
                'trigger' => 'failure_rate',
                'window' => '5m',
                'threshold' => 10,
                'channels' => ['slack'],
                'cooldown_minutes' => 15,
            ],
            [
                'trigger' => 'dlq_size',
                'threshold' => 20,
                'channels' => ['slack'],
                'cooldown_minutes' => 30,
            ],
        ]);

        self::assertCount(2, $rules);
        self::assertSame(AlertTrigger::FailureRate, $rules[0]->trigger);
        self::assertSame(AlertTrigger::DlqSize, $rules[1]->trigger);
    }

    public function test_empty_list_returns_empty_array(): void
    {
        self::assertSame([], (new AlertRuleFactory)->fromList([]));
    }

    public function test_unknown_trigger_throws(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('Unknown alert trigger');

        (new AlertRuleFactory)->fromArray([
            'trigger' => 'bogus',
            'window' => '5m',
            'threshold' => 1,
            'channels' => ['slack'],
            'cooldown_minutes' => 5,
        ]);
    }

    public function test_missing_trigger_key_throws(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('"trigger"');

        (new AlertRuleFactory)->fromArray([
            'window' => '5m',
            'threshold' => 1,
            'channels' => ['slack'],
            'cooldown_minutes' => 5,
        ]);
    }

    public function test_missing_threshold_is_treated_as_invalid(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('threshold');

        (new AlertRuleFactory)->fromArray([
            'trigger' => 'failure_rate',
            'window' => '5m',
            'channels' => ['slack'],
            'cooldown_minutes' => 5,
        ]);
    }

    public function test_missing_channels_is_treated_as_empty(): void
    {
        $this->expectException(InvalidAlertRule::class);
        $this->expectExceptionMessage('channel');

        (new AlertRuleFactory)->fromArray([
            'trigger' => 'failure_rate',
            'window' => '5m',
            'threshold' => 10,
            'cooldown_minutes' => 5,
        ]);
    }

    public function test_string_numeric_fields_are_coerced_to_int(): void
    {
        $factory = new AlertRuleFactory;

        // Hosts may pass env-originated strings
        $rule = $factory->fromArray([
            'trigger' => 'failure_rate',
            'window' => '5m',
            'threshold' => '10',
            'channels' => ['slack'],
            'cooldown_minutes' => '15',
        ]);

        self::assertSame(10, $rule->threshold);
        self::assertSame(15, $rule->cooldownMinutes);
    }
}
