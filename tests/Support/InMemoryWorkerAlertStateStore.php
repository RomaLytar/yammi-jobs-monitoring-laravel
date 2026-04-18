<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Application\Contract\WorkerAlertStateStore;

final class InMemoryWorkerAlertStateStore implements WorkerAlertStateStore
{
    /**
     * @var array<string, list<string>>
     */
    private array $state = [];

    public function active(string $category): array
    {
        return $this->state[$category] ?? [];
    }

    public function replace(string $category, array $keys): void
    {
        $this->state[$category] = array_values(array_unique($keys));
    }
}
