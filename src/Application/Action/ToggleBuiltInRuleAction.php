<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

/**
 * Flips the enabled flag for a single built-in rule.
 *
 * Routing rule:
 *  - When an override managed rule exists → its `enabled` field is flipped
 *    (the state row would be redundant and the UI shows one toggle).
 *  - Otherwise → the override is written to `built_in_rule_state`.
 *
 * Passing `null` clears the DB state override, restoring the code
 * default. When an override rule is active, `null` is treated as
 * "re-enable" since the managed rule's enabled flag must hold a value.
 */
final class ToggleBuiltInRuleAction
{
    public function __construct(
        private readonly BuiltInRuleStateRepository $stateRepo,
        private readonly ManagedAlertRuleRepository $rulesRepo,
    ) {}

    public function __invoke(string $key, ?bool $enabled): void
    {
        $override = $this->rulesRepo->findOverrideFor($key);
        if ($override !== null) {
            $this->rulesRepo->save($this->clone($override, $enabled ?? true));

            return;
        }

        if ($enabled === null) {
            $this->stateRepo->clear($key);

            return;
        }

        $this->stateRepo->setEnabled($key, $enabled);
    }

    private function clone(ManagedAlertRule $rule, bool $enabled): ManagedAlertRule
    {
        return new ManagedAlertRule(
            id: $rule->id(),
            key: $rule->key(),
            rule: $rule->rule(),
            enabled: $enabled,
            overridesBuiltIn: $rule->overridesBuiltIn(),
            position: $rule->position(),
        );
    }
}
