<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

/**
 * Pushes a raw, pre-encoded queue payload onto a named connection and
 * queue. The Application layer uses this port so it does not depend on
 * Illuminate\Contracts\Queue\Factory directly.
 */
interface QueueDispatcher
{
    public function pushRaw(string $connection, string $queue, string $rawPayload): void;
}
