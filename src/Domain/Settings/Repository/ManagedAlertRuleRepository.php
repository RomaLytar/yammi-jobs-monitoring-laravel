<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\Repository;

use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;

/**
 * Persistence boundary for user-managed alert rules.
 *
 * Rules are CRUDed via UI/API; ordering is preserved by `position`.
 * `key` is unique across user rules and identifies a rule for upsert.
 */
interface ManagedAlertRuleRepository
{
    /**
     * @return list<ManagedAlertRule>
     */
    public function all(): array;

    public function findById(int $id): ?ManagedAlertRule;

    public function findByKey(string $key): ?ManagedAlertRule;

    /**
     * Return the managed rule that replaces the given built-in (if any).
     *
     * At most one managed rule may override a given built-in key; the
     * first match is returned (ordering irrelevant — duplicates are a
     * bug to flag elsewhere).
     */
    public function findOverrideFor(string $builtInKey): ?ManagedAlertRule;

    /**
     * Persist the rule. If a rule with the same key already exists,
     * it is overwritten in place (id preserved). Otherwise inserted
     * and returned with its assigned id.
     */
    public function save(ManagedAlertRule $rule): ManagedAlertRule;

    public function delete(int $id): bool;
}
