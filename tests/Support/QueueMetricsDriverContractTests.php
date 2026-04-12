<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;

/**
 * Behaviour every QueueMetricsDriver implementation must satisfy.
 *
 * Concrete test classes (null, redis, database, …) `use` this trait and
 * provide createDriver(). Because it is a trait rather than an abstract
 * base class, unit tests can extend PHPUnit\Framework\TestCase directly
 * while integration tests can extend a Testbench-aware base — both share
 * the same contract suite.
 */
trait QueueMetricsDriverContractTests
{
    abstract protected function createDriver(): QueueMetricsDriver;

    public function test_get_queue_size_returns_nullable_int(): void
    {
        $result = $this->createDriver()->getQueueSize('default');

        self::assertTrue($result === null || is_int($result));
    }

    public function test_get_delayed_size_returns_nullable_int(): void
    {
        $result = $this->createDriver()->getDelayedSize('default');

        self::assertTrue($result === null || is_int($result));
    }

    public function test_get_reserved_size_returns_nullable_int(): void
    {
        $result = $this->createDriver()->getReservedSize('default');

        self::assertTrue($result === null || is_int($result));
    }
}
