<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Re-dispatch a dead-letter job on the host application's queue.
 *
 * The stored payload is required — we push it back raw via the queue
 * connection. If the host app runs without store_payload=true the
 * retry is refused since we have nothing to re-dispatch.
 */
final class RetryDeadLetterJobAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly QueueFactory $queue,
    ) {}

    public function __invoke(JobIdentifier $id): void
    {
        $attempts = $this->repository->findAllAttempts($id);

        if (count($attempts) === 0) {
            throw new RuntimeException(sprintf('No record found for job %s.', $id->value));
        }

        $latest = $attempts[count($attempts) - 1];
        $payload = $latest->payload();

        if ($payload === null) {
            throw new RuntimeException(
                'Cannot retry: payload not stored. Enable jobs-monitor.store_payload to use DLQ retry.',
            );
        }

        $this->queue
            ->connection($latest->connection)
            ->pushRaw($this->toRawPayload($latest, $payload), $latest->queue->value);
    }

    /**
     * @param  array<string|int, mixed>  $payload
     */
    private function toRawPayload(JobRecord $record, array $payload): string
    {
        // The stored payload was redacted for sensitive keys; anything still
        // present is JSON-serialisable. Laravel's queue workers expect the
        // envelope as a JSON string.
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException(sprintf('Failed to encode payload for job %s.', $record->id->value));
        }

        return $json;
    }
}
