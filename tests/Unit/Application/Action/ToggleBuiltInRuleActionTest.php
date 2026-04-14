<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\ToggleBuiltInRuleAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class ToggleBuiltInRuleActionTest extends TestCase
{
    public function test_sets_enabled_via_state_repo_when_no_override_exists(): void
    {
        $state = new InMemoryBuiltInRuleStateRepository;
        $rules = new InMemoryManagedAlertRuleRepository;

        (new ToggleBuiltInRuleAction($state, $rules))('critical_failure', false);

        self::assertFalse($state->findEnabled('critical_failure'));
    }

    public function test_null_clears_state_override(): void
    {
        $state = new InMemoryBuiltInRuleStateRepository;
        $state->setEnabled('critical_failure', false);
        $rules = new InMemoryManagedAlertRuleRepository;

        (new ToggleBuiltInRuleAction($state, $rules))('critical_failure', null);

        self::assertNull($state->findEnabled('critical_failure'));
    }

    public function test_updates_managed_override_enabled_when_override_exists(): void
    {
        $state = new InMemoryBuiltInRuleStateRepository;
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save(new ManagedAlertRule(
            id: null,
            key: 'override_critical',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureCategory,
                window: '5m', threshold: 3, channels: ['mail'],
                cooldownMinutes: 10, triggerValue: 'critical',
            ),
            enabled: true, overridesBuiltIn: 'critical_failure', position: 0,
        ));

        (new ToggleBuiltInRuleAction($state, $rules))('critical_failure', false);

        $updated = $rules->findOverrideFor('critical_failure');
        self::assertNotNull($updated);
        self::assertFalse($updated->isEnabled());
        self::assertNull($state->findEnabled('critical_failure'));
    }
}
