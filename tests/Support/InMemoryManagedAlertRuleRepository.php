<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;

final class InMemoryManagedAlertRuleRepository implements ManagedAlertRuleRepository
{
    /** @var array<int, ManagedAlertRule> */
    private array $rows = [];

    private int $nextId = 1;

    public function all(): array
    {
        $rows = array_values($this->rows);
        usort(
            $rows,
            static fn (ManagedAlertRule $a, ManagedAlertRule $b): int => $a->position() <=> $b->position(),
        );

        return $rows;
    }

    public function findById(int $id): ?ManagedAlertRule
    {
        return $this->rows[$id] ?? null;
    }

    public function findByKey(string $key): ?ManagedAlertRule
    {
        foreach ($this->rows as $row) {
            if ($row->key() === $key) {
                return $row;
            }
        }

        return null;
    }

    public function save(ManagedAlertRule $rule): ManagedAlertRule
    {
        $existing = $this->findByKey($rule->key());

        if ($existing !== null) {
            $persisted = $rule->withId((int) $existing->id());
            $this->rows[(int) $existing->id()] = $persisted;

            return $persisted;
        }

        $id = $this->nextId++;
        $persisted = $rule->withId($id);
        $this->rows[$id] = $persisted;

        return $persisted;
    }

    public function delete(int $id): bool
    {
        if (! isset($this->rows[$id])) {
            return false;
        }

        unset($this->rows[$id]);

        return true;
    }
}
