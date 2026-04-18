<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Application\Contract\QueueDispatcher;

/**
 * In-memory QueueDispatcher that captures each pushRaw call. Used by
 * unit tests that need to assert on the dispatched payload without
 * standing up the Laravel queue stack.
 */
final class RecordingQueueDispatcher implements QueueDispatcher
{
    /** @var list<array{connection: string, queue: string, payload: string}> */
    public array $pushed = [];

    public function pushRaw(string $connection, string $queue, string $rawPayload): void
    {
        $this->pushed[] = [
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $rawPayload,
        ];
    }
}
