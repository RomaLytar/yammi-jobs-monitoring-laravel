<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Application\Contract\HeartbeatRateLimiter;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

/**
 * Deterministic rate limiter fake. The first `attempt()` for a given
 * worker returns true; subsequent calls return false until reset().
 */
final class ArrayHeartbeatRateLimiter implements HeartbeatRateLimiter
{
    /**
     * @var array<string, true>
     */
    private array $seen = [];

    public function attempt(WorkerIdentifier $id, int $intervalSeconds): bool
    {
        if (isset($this->seen[$id->value])) {
            return false;
        }

        $this->seen[$id->value] = true;

        return true;
    }

    public function reset(): void
    {
        $this->seen = [];
    }
}
