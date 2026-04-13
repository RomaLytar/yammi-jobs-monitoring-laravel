<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\ListAlertRulesAction;
use Yammi\JobsMonitor\Application\DTO\BuiltInRuleData;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class ListAlertRulesActionTest extends TestCase
{
    public function test_returns_built_ins_with_code_defaults_and_effective_enabled(): void
    {
        $overview = $this->buildAction()();

        $keys = array_map(static fn ($r) => $r->key, $overview->builtInRules);
        self::assertContains('critical_failure', $keys);

        $critical = $this->byKey($overview->builtInRules, 'critical_failure');
        self::assertTrue($critical->effectivelyEnabled);
        self::assertTrue($critical->codeDefaultEnabled);
        self::assertFalse($critical->hasOverride);
        self::assertNull($critical->overrideRuleId);
        self::assertSame(1, $critical->threshold);
    }

    public function test_state_override_flips_effective_enabled(): void
    {
        $state = new InMemoryBuiltInRuleStateRepository;
        $state->setEnabled('critical_failure', false);

        $overview = $this->buildAction(state: $state)();

        $critical = $this->byKey($overview->builtInRules, 'critical_failure');
        self::assertFalse($critical->effectivelyEnabled);
        self::assertTrue($critical->hasOverride);
        self::assertNull($critical->overrideRuleId);
    }

    public function test_managed_override_rule_replaces_built_in_values(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $override = $rules->save(new ManagedAlertRule(
            id: null,
            key: 'built_in_override_critical_failure',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureCategory,
                window: '15m',
                threshold: 99,
                channels: ['mail'],
                cooldownMinutes: 60,
                triggerValue: 'critical',
                minAttempt: 2,
            ),
            enabled: false,
            overridesBuiltIn: 'critical_failure',
            position: 0,
        ));

        $overview = $this->buildAction(rules: $rules)();

        $critical = $this->byKey($overview->builtInRules, 'critical_failure');
        self::assertSame(99, $critical->threshold);
        self::assertSame('15m', $critical->window);
        self::assertSame(['mail'], $critical->channels);
        self::assertSame(60, $critical->cooldownMinutes);
        self::assertSame(2, $critical->minAttempt);
        self::assertFalse($critical->effectivelyEnabled);
        self::assertTrue($critical->hasOverride);
        self::assertSame($override->id(), $critical->overrideRuleId);
    }

    public function test_user_rules_exclude_built_in_overrides(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save(new ManagedAlertRule(
            id: null, key: 'my_custom',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '5m', threshold: 50,
                channels: ['slack'], cooldownMinutes: 10,
            ),
            enabled: true, overridesBuiltIn: null, position: 0,
        ));
        $rules->save(new ManagedAlertRule(
            id: null, key: 'override_critical',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureCategory,
                window: '5m', threshold: 1,
                channels: ['mail'], cooldownMinutes: 10,
                triggerValue: 'critical',
            ),
            enabled: true, overridesBuiltIn: 'critical_failure', position: 0,
        ));

        $overview = $this->buildAction(rules: $rules)();

        $userKeys = array_map(static fn (ManagedAlertRule $r) => $r->key(), $overview->userRules);
        self::assertSame(['my_custom'], $userKeys);
    }

    private function buildAction(
        ?InMemoryBuiltInRuleStateRepository $state = null,
        ?InMemoryManagedAlertRuleRepository $rules = null,
    ): ListAlertRulesAction {
        return new ListAlertRulesAction(
            builtInProvider: new BuiltInRulesProvider(new AlertRuleFactory),
            builtInState: $state ?? new InMemoryBuiltInRuleStateRepository,
            rulesRepo: $rules ?? new InMemoryManagedAlertRuleRepository,
        );
    }

    /**
     * @param  list<BuiltInRuleData>  $rules
     */
    private function byKey(array $rules, string $key): BuiltInRuleData
    {
        foreach ($rules as $r) {
            if ($r->key === $key) {
                return $r;
            }
        }
        self::fail(sprintf('Built-in rule "%s" not in overview', $key));
    }
}
