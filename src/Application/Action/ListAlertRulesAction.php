<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\DTO\AlertRulesOverviewData;
use Yammi\JobsMonitor\Application\DTO\BuiltInRuleData;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

/**
 * Returns the unified alert-rule overview rendered on the settings page.
 *
 * Each built-in row reflects effective values after merging:
 *  1. the shipped code default, then
 *  2. the user's field overrides (ManagedAlertRule with `overridesBuiltIn`), then
 *  3. the enabled-only state override (when no managed override exists).
 *
 * User rules (managed rules NOT overriding a built-in) are returned
 * verbatim so API clients can still CRUD them; the UI hides that
 * section entirely for safety.
 */
final class ListAlertRulesAction
{
    public function __construct(
        private readonly BuiltInRulesProvider $builtInProvider,
        private readonly BuiltInRuleStateRepository $builtInState,
        private readonly ManagedAlertRuleRepository $rulesRepo,
    ) {}

    public function __invoke(): AlertRulesOverviewData
    {
        $allManaged = $this->rulesRepo->all();
        $overridesByKey = $this->indexOverridesByBuiltInKey($allManaged);
        $stateOverrides = $this->builtInState->all();

        $builtIns = [];
        foreach ($this->builtInProvider->catalog() as $key => $defaults) {
            $builtIns[] = $this->buildBuiltInData(
                key: (string) $key,
                defaults: $defaults,
                override: $overridesByKey[$key] ?? null,
                stateOverride: $stateOverrides[$key] ?? null,
            );
        }

        return new AlertRulesOverviewData(
            builtInRules: $builtIns,
            userRules: $this->userOnlyRules($allManaged),
        );
    }

    /**
     * @param  list<ManagedAlertRule>  $managed
     * @return array<string, ManagedAlertRule>
     */
    private function indexOverridesByBuiltInKey(array $managed): array
    {
        $map = [];
        foreach ($managed as $rule) {
            $key = $rule->overridesBuiltIn();
            if ($key !== null) {
                $map[$key] = $rule;
            }
        }

        return $map;
    }

    /**
     * @param  list<ManagedAlertRule>  $managed
     * @return list<ManagedAlertRule>
     */
    private function userOnlyRules(array $managed): array
    {
        $rules = [];
        foreach ($managed as $rule) {
            if ($rule->overridesBuiltIn() === null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private function buildBuiltInData(
        string $key,
        array $defaults,
        ?ManagedAlertRule $override,
        ?bool $stateOverride,
    ): BuiltInRuleData {
        $codeDefaultEnabled = (bool) ($defaults['enabled'] ?? false);

        if ($override !== null) {
            $rule = $override->rule();

            return new BuiltInRuleData(
                key: $key,
                trigger: $rule->trigger,
                triggerValue: $rule->triggerValue,
                window: $rule->window,
                threshold: $rule->threshold,
                cooldownMinutes: $rule->cooldownMinutes,
                minAttempt: $rule->minAttempt,
                channels: $rule->channels,
                codeDefaultEnabled: $codeDefaultEnabled,
                effectivelyEnabled: $override->isEnabled(),
                hasOverride: true,
                overrideRuleId: $override->id(),
            );
        }

        /** @var list<string> $channels */
        $channels = array_values((array) ($defaults['channels'] ?? []));

        return new BuiltInRuleData(
            key: $key,
            trigger: AlertTrigger::from((string) $defaults['trigger']),
            triggerValue: isset($defaults['value']) ? (string) $defaults['value'] : null,
            window: isset($defaults['window']) ? (string) $defaults['window'] : null,
            threshold: (int) $defaults['threshold'],
            cooldownMinutes: (int) $defaults['cooldown_minutes'],
            minAttempt: isset($defaults['min_attempt']) ? (int) $defaults['min_attempt'] : null,
            channels: $channels,
            codeDefaultEnabled: $codeDefaultEnabled,
            effectivelyEnabled: $stateOverride ?? $codeDefaultEnabled,
            hasOverride: $stateOverride !== null,
            overrideRuleId: null,
        );
    }
}
