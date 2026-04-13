<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class AlertConfigResolverTest extends TestCase
{
    public function test_disabled_when_db_unset_and_config_disabled(): void
    {
        $resolver = $this->buildResolver(configEnabled: false);

        $config = $resolver->resolve();

        self::assertFalse($config->enabled);
        self::assertSame([], $config->rules);
    }

    public function test_enabled_when_db_unset_and_config_enabled(): void
    {
        $resolver = $this->buildResolver(configEnabled: true);

        self::assertTrue($resolver->resolve()->enabled);
    }

    public function test_db_enabled_true_overrides_config_disabled(): void
    {
        $settings = new InMemoryAlertSettingsRepository;
        $settings->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $resolver = $this->buildResolver(configEnabled: false, settingsRepo: $settings);

        self::assertTrue($resolver->resolve()->enabled);
    }

    public function test_db_enabled_false_overrides_config_enabled(): void
    {
        $settings = new InMemoryAlertSettingsRepository;
        $settings->save(new AlertSettings(false, null, null, new EmailRecipientList([])));

        $resolver = $this->buildResolver(configEnabled: true, settingsRepo: $settings);

        self::assertFalse($resolver->resolve()->enabled);
    }

    public function test_disabled_resolution_returns_no_rules_even_if_rules_exist(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save($this->managedRule('user_rule'));

        $resolver = $this->buildResolver(configEnabled: false, rulesRepo: $rules);

        self::assertSame([], $resolver->resolve()->rules);
    }

    public function test_built_in_default_enabled_rules_are_included(): void
    {
        $resolver = $this->buildResolver(configEnabled: true);

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        // critical_failure (failure_category) and retry_storm (failure_rate) ship enabled
        self::assertContains('failure_category:critical', $keys);
        self::assertContains('failure_rate:5', $keys);
    }

    public function test_built_in_default_disabled_rules_are_excluded(): void
    {
        $resolver = $this->buildResolver(configEnabled: true);

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        // dlq_growing and high_failure_rate ship disabled
        self::assertNotContains('dlq_size:10', $keys);
        self::assertNotContains('failure_rate:20', $keys);
    }

    public function test_user_can_disable_built_in_rule_via_db(): void
    {
        $state = new InMemoryBuiltInRuleStateRepository;
        $state->setEnabled('critical_failure', false);

        $resolver = $this->buildResolver(configEnabled: true, builtInState: $state);

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        self::assertNotContains('failure_category:critical', $keys);
    }

    public function test_user_can_enable_default_disabled_built_in_via_db(): void
    {
        $state = new InMemoryBuiltInRuleStateRepository;
        $state->setEnabled('dlq_growing', true);

        $resolver = $this->buildResolver(configEnabled: true, builtInState: $state);

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        self::assertContains('dlq_size:10', $keys);
    }

    public function test_managed_user_rule_is_included_when_enabled(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save($this->managedRule('user_rule', threshold: 99));

        $resolver = $this->buildResolver(configEnabled: true, rulesRepo: $rules);

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        self::assertContains('failure_rate:99', $keys);
    }

    public function test_managed_user_rule_is_skipped_when_disabled(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save(new ManagedAlertRule(
            id: null,
            key: 'disabled_rule',
            rule: $this->failureRateAlertRule(threshold: 77),
            enabled: false,
            overridesBuiltIn: null,
            position: 0,
        ));

        $resolver = $this->buildResolver(configEnabled: true, rulesRepo: $rules);

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        self::assertNotContains('failure_rate:77', $keys);
    }

    public function test_managed_rule_overriding_built_in_suppresses_built_in(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save(new ManagedAlertRule(
            id: null,
            key: 'my_critical',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureCategory,
                window: '1m',
                threshold: 1,
                channels: ['mail'],
                cooldownMinutes: 1,
                triggerValue: 'critical',
            ),
            enabled: true,
            overridesBuiltIn: 'critical_failure',
            position: 0,
        ));

        $resolver = $this->buildResolver(configEnabled: true, rulesRepo: $rules);

        $resolved = $resolver->resolve()->rules;
        $keys = $this->triggerKeys($resolved);

        // Exactly one rule for failure_category:critical (the user one), not two
        self::assertSame(
            1,
            count(array_filter($keys, static fn (string $k): bool => $k === 'failure_category:critical')),
        );
        // And it's the user rule (channels: ['mail'])
        $critical = $this->ruleByKey($resolved, 'failure_category:critical');
        self::assertSame(['mail'], $critical->channels);
    }

    public function test_disabled_managed_rule_with_override_still_suppresses_built_in(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save(new ManagedAlertRule(
            id: null,
            key: 'my_critical',
            rule: $this->failureCategoryRule('critical'),
            enabled: false,
            overridesBuiltIn: 'critical_failure',
            position: 0,
        ));

        $resolver = $this->buildResolver(configEnabled: true, rulesRepo: $rules);

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        self::assertNotContains('failure_category:critical', $keys);
    }

    public function test_config_custom_rules_used_when_no_db_managed_rules_exist(): void
    {
        $resolver = $this->buildResolver(
            configEnabled: true,
            configCustomRules: [
                [
                    'trigger' => 'failure_rate',
                    'window' => '1m',
                    'threshold' => 42,
                    'cooldown_minutes' => 10,
                    'channels' => ['slack'],
                ],
            ],
        );

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        self::assertContains('failure_rate:42', $keys);
    }

    public function test_config_custom_rules_ignored_when_db_has_any_managed_rule(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save($this->managedRule('any_user_rule', threshold: 11));

        $resolver = $this->buildResolver(
            configEnabled: true,
            rulesRepo: $rules,
            configCustomRules: [
                [
                    'trigger' => 'failure_rate',
                    'window' => '1m',
                    'threshold' => 42,
                    'cooldown_minutes' => 10,
                    'channels' => ['slack'],
                ],
            ],
        );

        $keys = $this->triggerKeys($resolver->resolve()->rules);

        self::assertContains('failure_rate:11', $keys);
        self::assertNotContains('failure_rate:42', $keys);
    }

    public function test_resolution_order_is_built_ins_then_user_rules(): void
    {
        $rules = new InMemoryManagedAlertRuleRepository;
        $rules->save($this->managedRule('zzz_late_user_rule', threshold: 31));

        $resolver = $this->buildResolver(configEnabled: true, rulesRepo: $rules);

        $resolved = $resolver->resolve()->rules;
        $keys = $this->triggerKeys($resolved);

        // failure_category:critical (built-in) appears before the user rule
        $criticalIndex = array_search('failure_category:critical', $keys, true);
        $userIndex = array_search('failure_rate:31', $keys, true);

        self::assertNotFalse($criticalIndex);
        self::assertNotFalse($userIndex);
        self::assertLessThan($userIndex, $criticalIndex);
    }

    private function buildResolver(
        bool $configEnabled = false,
        ?InMemoryAlertSettingsRepository $settingsRepo = null,
        ?InMemoryManagedAlertRuleRepository $rulesRepo = null,
        ?InMemoryBuiltInRuleStateRepository $builtInState = null,
        array $builtInConfigOverrides = [],
        array $configCustomRules = [],
    ): AlertConfigResolver {
        $factory = new AlertRuleFactory;

        return new AlertConfigResolver(
            settingsRepo: $settingsRepo ?? new InMemoryAlertSettingsRepository,
            rulesRepo: $rulesRepo ?? new InMemoryManagedAlertRuleRepository,
            builtInStateRepo: $builtInState ?? new InMemoryBuiltInRuleStateRepository,
            builtInRulesProvider: new BuiltInRulesProvider($factory),
            ruleFactory: $factory,
            configEnabled: $configEnabled,
            builtInConfigOverrides: $builtInConfigOverrides,
            configCustomRules: $configCustomRules,
        );
    }

    private function managedRule(string $key, int $threshold = 10): ManagedAlertRule
    {
        return new ManagedAlertRule(
            id: null,
            key: $key,
            rule: $this->failureRateAlertRule($threshold),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );
    }

    private function failureRateAlertRule(int $threshold): AlertRule
    {
        return new AlertRule(
            trigger: AlertTrigger::FailureRate,
            window: '5m',
            threshold: $threshold,
            channels: ['slack'],
            cooldownMinutes: 10,
        );
    }

    private function failureCategoryRule(string $category): AlertRule
    {
        return new AlertRule(
            trigger: AlertTrigger::FailureCategory,
            window: '1m',
            threshold: 1,
            channels: ['slack'],
            cooldownMinutes: 5,
            triggerValue: $category,
        );
    }

    /**
     * @param  list<AlertRule>  $rules
     * @return list<string>
     */
    private function triggerKeys(array $rules): array
    {
        return array_map(
            static fn (AlertRule $r): string => $r->triggerValue !== null
                ? sprintf('%s:%s', $r->trigger->value, $r->triggerValue)
                : sprintf('%s:%d', $r->trigger->value, $r->threshold),
            $rules,
        );
    }

    /**
     * @param  list<AlertRule>  $rules
     */
    private function ruleByKey(array $rules, string $key): AlertRule
    {
        foreach ($rules as $r) {
            $current = $r->triggerValue !== null
                ? sprintf('%s:%s', $r->trigger->value, $r->triggerValue)
                : sprintf('%s:%d', $r->trigger->value, $r->threshold);

            if ($current === $key) {
                return $r;
            }
        }

        self::fail(sprintf('Rule with key "%s" not found', $key));
    }
}
