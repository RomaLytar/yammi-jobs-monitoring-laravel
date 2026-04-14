<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use Yammi\JobsMonitor\Application\DTO\AlertRuntimeConfig;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

/**
 * Resolves the effective alert configuration each evaluation tick.
 *
 * Resolution rule for every value: DB row > config value > "feature off".
 *
 * Rule merging:
 *  - Built-ins ship enabled-by-default (per BuiltInRulesProvider). User
 *    can toggle each via the BuiltInRuleStateRepository or replace one
 *    entirely by saving a managed rule with overridesBuiltIn = key.
 *  - Managed (DB) rules are appended after surviving built-ins.
 *  - Config-side custom_rules are used ONLY when the DB has no managed
 *    rules at all; the moment one DB rule is created, config rules are
 *    silenced (predictable for operators editing in UI).
 */
final class AlertConfigResolver
{
    /**
     * @param  array<string, array<string, mixed>>  $builtInConfigOverrides  Overrides for built-in rule fields, keyed by rule id
     * @param  list<array<string, mixed>>  $configCustomRules  Raw rule arrays from config
     */
    public function __construct(
        private readonly AlertSettingsRepository $settingsRepo,
        private readonly ManagedAlertRuleRepository $rulesRepo,
        private readonly BuiltInRuleStateRepository $builtInStateRepo,
        private readonly BuiltInRulesProvider $builtInRulesProvider,
        private readonly AlertRuleFactory $ruleFactory,
        private readonly bool $configEnabled,
        private readonly array $builtInConfigOverrides,
        private readonly array $configCustomRules,
    ) {}

    public function resolve(): AlertRuntimeConfig
    {
        if (! $this->resolveEnabled()) {
            return new AlertRuntimeConfig(false, []);
        }

        return new AlertRuntimeConfig(true, $this->resolveRules());
    }

    private function resolveEnabled(): bool
    {
        $dbValue = $this->settingsRepo->get()->isEnabled();

        return $dbValue ?? $this->configEnabled;
    }

    /**
     * @return list<AlertRule>
     */
    private function resolveRules(): array
    {
        $managed = $this->rulesRepo->all();

        return array_values(array_merge(
            $this->resolveBuiltIns($managed),
            $this->resolveUserRules($managed),
            $this->resolveConfigCustomRules($managed),
        ));
    }

    /**
     * @param  list<ManagedAlertRule>  $managed
     * @return list<AlertRule>
     */
    private function resolveBuiltIns(array $managed): array
    {
        $overriddenKeys = $this->keysOverriddenByUser($managed);
        $stateOverrides = $this->builtInStateRepo->all();

        $result = [];

        foreach ($this->builtInRulesProvider->catalog() as $key => $default) {
            if (isset($overriddenKeys[$key])) {
                continue;
            }

            $merged = array_replace($default, $this->builtInConfigOverrides[$key] ?? []);

            $effectiveEnabled = $stateOverrides[$key] ?? (bool) ($merged['enabled'] ?? false);
            if (! $effectiveEnabled) {
                continue;
            }

            unset($merged['enabled']);
            $result[] = $this->ruleFactory->fromArray($merged);
        }

        return $result;
    }

    /**
     * @param  list<ManagedAlertRule>  $managed
     * @return list<AlertRule>
     */
    private function resolveUserRules(array $managed): array
    {
        $result = [];

        foreach ($managed as $row) {
            if (! $row->isEnabled()) {
                continue;
            }

            $result[] = $row->rule();
        }

        return $result;
    }

    /**
     * @param  list<ManagedAlertRule>  $managed
     * @return list<AlertRule>
     */
    private function resolveConfigCustomRules(array $managed): array
    {
        if ($managed !== []) {
            return [];
        }

        return $this->ruleFactory->fromList($this->configCustomRules);
    }

    /**
     * @param  list<ManagedAlertRule>  $managed
     * @return array<string, true>
     */
    private function keysOverriddenByUser(array $managed): array
    {
        $keys = [];

        foreach ($managed as $row) {
            $key = $row->overridesBuiltIn();
            if ($key !== null) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }
}
