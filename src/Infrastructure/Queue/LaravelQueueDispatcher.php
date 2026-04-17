<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Queue;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Yammi\JobsMonitor\Application\Contract\QueueDispatcher;

final class LaravelQueueDispatcher implements QueueDispatcher
{
    public function __construct(private readonly QueueFactory $factory) {}

    public function pushRaw(string $connection, string $queue, string $rawPayload): void
    {
        $this->factory->connection($connection)->pushRaw($rawPayload, $queue);
    }
}
