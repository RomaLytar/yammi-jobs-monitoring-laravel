<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\SaveManagedAlertRuleAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class SaveManagedAlertRuleActionTest extends TestCase
{
    public function test_saves_new_rule_and_returns_with_id(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $action = new SaveManagedAlertRuleAction($repo);

        $rule = $this->makeRule('new-rule');

        $result = $action($rule);

        self::assertTrue($result->isPersisted());
        self::assertNotNull($result->id());
        self::assertSame('new-rule', $result->key());
    }

    public function test_updates_existing_rule_with_same_key(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $action = new SaveManagedAlertRuleAction($repo);

        $original = $this->makeRule('my-rule');
        $saved = $action($original);
        $originalId = $saved->id();

        $updated = new ManagedAlertRule(
            id: null,
            key: 'my-rule',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '10m',
                threshold: 20,
                channels: ['slack', 'mail'],
                cooldownMinutes: 30,
            ),
            enabled: false,
            overridesBuiltIn: null,
            position: 1,
        );

        $result = $action($updated);

        self::assertSame($originalId, $result->id());
        self::assertSame('my-rule', $result->key());
        self::assertFalse($result->isEnabled());
        self::assertSame(20, $result->rule()->threshold);
    }

    public function test_saves_multiple_rules_with_different_keys(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $action = new SaveManagedAlertRuleAction($repo);

        $first = $action($this->makeRule('rule-a'));
        $second = $action($this->makeRule('rule-b'));

        self::assertNotSame($first->id(), $second->id());
        self::assertCount(2, $repo->all());
    }

    public function test_returned_entity_has_correct_rule_properties(): void
    {
        $repo = new InMemoryManagedAlertRuleRepository;
        $action = new SaveManagedAlertRuleAction($repo);

        $rule = new ManagedAlertRule(
            id: null,
            key: 'detailed-rule',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '15m',
                threshold: 5,
                channels: ['slack'],
                cooldownMinutes: 60,
            ),
            enabled: true,
            overridesBuiltIn: 'critical_failure',
            position: 3,
        );

        $result = $action($rule);

        self::assertSame('detailed-rule', $result->key());
        self::assertTrue($result->isEnabled());
        self::assertSame('critical_failure', $result->overridesBuiltIn());
        self::assertSame(3, $result->position());
        self::assertSame(5, $result->rule()->threshold);
    }

    private function makeRule(string $key): ManagedAlertRule
    {
        return new ManagedAlertRule(
            id: null,
            key: $key,
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '5m',
                threshold: 10,
                channels: ['slack'],
                cooldownMinutes: 15,
            ),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );
    }
}
