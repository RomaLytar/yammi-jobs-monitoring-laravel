<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Metrics;

use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;

/**
 * No-op implementation for queue drivers that cannot provide
 * metrics (e.g. sync). Every method returns null.
 */
final class NullMetricsDriver implements QueueMetricsDriver
{
    public function getQueueSize(string $queue): ?int
    {
        return null;
    }

    public function getDelayedSize(string $queue): ?int
    {
        return null;
    }

    public function getReservedSize(string $queue): ?int
    {
        return null;
    }
}
