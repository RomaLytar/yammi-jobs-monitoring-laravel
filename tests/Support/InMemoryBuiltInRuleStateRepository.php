<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;

final class InMemoryBuiltInRuleStateRepository implements BuiltInRuleStateRepository
{
    /** @var array<string, bool> */
    private array $state = [];

    public function findEnabled(string $key): ?bool
    {
        return $this->state[$key] ?? null;
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $this->state[$key] = $enabled;
    }

    public function clear(string $key): void
    {
        unset($this->state[$key]);
    }

    public function all(): array
    {
        return $this->state;
    }
}
