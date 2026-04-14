<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

/**
 * Reverts a built-in rule back to its shipped defaults.
 *
 * Deletes the managed override rule if one exists and clears any
 * enabled-state override. After reset the row is indistinguishable
 * from a fresh install.
 */
final class ResetBuiltInRuleAction
{
    public function __construct(
        private readonly ManagedAlertRuleRepository $rulesRepo,
        private readonly BuiltInRuleStateRepository $stateRepo,
    ) {}

    public function __invoke(string $builtInKey): void
    {
        $override = $this->rulesRepo->findOverrideFor($builtInKey);
        if ($override !== null && $override->isPersisted()) {
            $this->rulesRepo->delete((int) $override->id());
        }

        $this->stateRepo->clear($builtInKey);
    }
}
