<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

/**
 * Port for driver-specific queue metrics (queue depth, delayed/reserved counts).
 *
 * Each queue driver (redis, database, sqs, …) provides its own
 * implementation. Drivers that cannot supply a metric return null,
 * and the consuming layer (UI / API) hides the corresponding widget.
 */
interface QueueMetricsDriver
{
    public function getQueueSize(string $queue): ?int;

    public function getDelayedSize(string $queue): ?int;

    public function getReservedSize(string $queue): ?int;
}
