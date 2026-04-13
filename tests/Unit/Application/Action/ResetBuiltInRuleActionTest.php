<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\ResetBuiltInRuleAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class ResetBuiltInRuleActionTest extends TestCase
{
    public function test_deletes_override_rule_and_clears_state(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $persisted = $rules->save(new ManagedAlertRule(
            id: null, key: 'built_in_override_critical_failure',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureCategory,
                window: '5m', threshold: 3, channels: ['mail'],
                cooldownMinutes: 15, triggerValue: 'critical',
            ),
            enabled: false, overridesBuiltIn: 'critical_failure', position: 0,
        ));
        $state = new InMemoryBuiltInRuleStateRepository;
        $state->setEnabled('critical_failure', false);

        (new ResetBuiltInRuleAction($rules, $state))('critical_failure');

        self::assertNull($rules->findById((int) $persisted->id()));
        self::assertNull($state->findEnabled('critical_failure'));
    }

    public function test_reset_without_any_override_is_noop(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $state = new InMemoryBuiltInRuleStateRepository;

        (new ResetBuiltInRuleAction($rules, $state))('critical_failure');

        self::assertSame([], $rules->all());
    }
}
