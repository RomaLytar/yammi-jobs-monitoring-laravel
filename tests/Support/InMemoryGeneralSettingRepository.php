<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;

final class InMemoryGeneralSettingRepository implements GeneralSettingRepository
{
    /** @var array<string, array<string, string>> */
    private array $store = [];

    public function all(): array
    {
        return $this->store;
    }

    public function get(string $group, string $key): ?string
    {
        return $this->store[$group][$key] ?? null;
    }

    public function set(string $group, string $key, string $value, string $type): void
    {
        $this->store[$group][$key] = $value;
    }

    public function remove(string $group, string $key): void
    {
        unset($this->store[$group][$key]);

        if (isset($this->store[$group]) && $this->store[$group] === []) {
            unset($this->store[$group]);
        }
    }
}
