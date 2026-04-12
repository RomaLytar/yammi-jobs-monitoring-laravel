<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Metrics;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Tests\Support\QueueMetricsDriverContractTests;

final class NullMetricsDriverTest extends TestCase
{
    use QueueMetricsDriverContractTests;

    protected function createDriver(): QueueMetricsDriver
    {
        return new NullMetricsDriver;
    }

    public function test_get_queue_size_always_returns_null(): void
    {
        self::assertNull((new NullMetricsDriver)->getQueueSize('default'));
        self::assertNull((new NullMetricsDriver)->getQueueSize('emails'));
    }

    public function test_get_delayed_size_always_returns_null(): void
    {
        self::assertNull((new NullMetricsDriver)->getDelayedSize('default'));
        self::assertNull((new NullMetricsDriver)->getDelayedSize('emails'));
    }

    public function test_get_reserved_size_always_returns_null(): void
    {
        self::assertNull((new NullMetricsDriver)->getReservedSize('default'));
        self::assertNull((new NullMetricsDriver)->getReservedSize('emails'));
    }
}
