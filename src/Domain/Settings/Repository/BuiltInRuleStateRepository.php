<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\Repository;

/**
 * Persistence boundary for user-defined enabled-state overrides on
 * built-in alert rules.
 *
 * Absence of a row for a given key means "no override — use the code default".
 */
interface BuiltInRuleStateRepository
{
    public function findEnabled(string $key): ?bool;

    public function setEnabled(string $key, bool $enabled): void;

    public function clear(string $key): void;

    /**
     * @return array<string, bool>
     */
    public function all(): array;
}
