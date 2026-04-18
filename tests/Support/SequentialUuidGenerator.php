<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Application\Contract\UuidGenerator;

/**
 * UuidGenerator that returns deterministic, incrementing UUIDs. Lets
 * tests assert on the new-UUID value without flakiness.
 */
final class SequentialUuidGenerator implements UuidGenerator
{
    private int $counter = 0;

    public function generate(): string
    {
        $this->counter++;

        return sprintf('00000000-0000-4000-8000-%012d', $this->counter);
    }
}
