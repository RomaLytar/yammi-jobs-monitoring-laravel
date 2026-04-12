<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\Contract;

/**
 * Deduplicates alert delivery within a cooldown window.
 *
 * The primitive is a single atomic check-and-set: attempt() returns true
 * only if no sibling evaluator has fired the rule inside the cooldown
 * window, and simultaneously reserves the window. This prevents races
 * between two scheduled evaluators hitting the same rule at once.
 */
interface AlertThrottle
{
    /**
     * Atomically reserve the cooldown window for this rule.
     *
     * Returns true when the caller is cleared to dispatch the alert.
     * Returns false when the rule is still cooling down from a prior
     * dispatch — the caller must not send in that case.
     */
    public function attempt(string $ruleKey, int $cooldownMinutes): bool;
}
