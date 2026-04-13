<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Settings\Entity;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidManagedAlertRule;

final class ManagedAlertRuleTest extends TestCase
{
    public function test_constructs_with_all_fields_set(): void
    {
        $rule = new ManagedAlertRule(
            id: 7,
            key: 'critical_failure',
            rule: $this->sampleAlertRule(),
            enabled: true,
            overridesBuiltIn: 'critical_failure',
            position: 0,
        );

        self::assertSame(7, $rule->id());
        self::assertSame('critical_failure', $rule->key());
        self::assertSame($this->sampleAlertRule()->trigger, $rule->rule()->trigger);
        self::assertTrue($rule->isEnabled());
        self::assertSame('critical_failure', $rule->overridesBuiltIn());
        self::assertSame(0, $rule->position());
        self::assertTrue($rule->isPersisted());
    }

    public function test_unpersisted_rule_has_null_id(): void
    {
        $rule = new ManagedAlertRule(
            id: null,
            key: 'my_rule',
            rule: $this->sampleAlertRule(),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );

        self::assertNull($rule->id());
        self::assertFalse($rule->isPersisted());
        self::assertNull($rule->overridesBuiltIn());
    }

    public function test_empty_key_is_rejected(): void
    {
        $this->expectException(InvalidManagedAlertRule::class);
        $this->expectExceptionMessage('key');

        new ManagedAlertRule(
            id: null,
            key: '',
            rule: $this->sampleAlertRule(),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );
    }

    public function test_whitespace_key_is_rejected(): void
    {
        $this->expectException(InvalidManagedAlertRule::class);

        new ManagedAlertRule(
            id: null,
            key: '   ',
            rule: $this->sampleAlertRule(),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );
    }

    public function test_negative_position_is_rejected(): void
    {
        $this->expectException(InvalidManagedAlertRule::class);
        $this->expectExceptionMessage('position');

        new ManagedAlertRule(
            id: null,
            key: 'my_rule',
            rule: $this->sampleAlertRule(),
            enabled: true,
            overridesBuiltIn: null,
            position: -1,
        );
    }

    public function test_with_id_returns_persisted_clone(): void
    {
        $original = new ManagedAlertRule(
            id: null,
            key: 'my_rule',
            rule: $this->sampleAlertRule(),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );

        $persisted = $original->withId(42);

        self::assertNull($original->id());
        self::assertSame(42, $persisted->id());
        self::assertSame('my_rule', $persisted->key());
    }

    private function sampleAlertRule(): AlertRule
    {
        return new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: 10,
            channels: ['slack'],
            cooldownMinutes: 15,
        );
    }
}
