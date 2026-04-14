<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

/**
 * Persists a managed alert rule (create or update).
 *
 * Repository upserts by `key` — a rule with the same key as an existing
 * one is updated in place, preserving its id. Returns the persisted
 * entity with id assigned.
 */
final class SaveManagedAlertRuleAction
{
    public function __construct(
        private readonly ManagedAlertRuleRepository $repo,
    ) {}

    public function __invoke(ManagedAlertRule $rule): ManagedAlertRule
    {
        return $this->repo->save($rule);
    }
}
