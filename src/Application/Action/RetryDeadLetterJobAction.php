<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use RuntimeException;
use Yammi\JobsMonitor\Application\Contract\QueueDispatcher;
use Yammi\JobsMonitor\Application\Contract\UuidGenerator;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Re-dispatch a dead-letter job on the host application's queue.
 *
 * The stored payload is required — we push it back raw via the queue
 * port with a fresh UUID so the new run is tracked as its own lifecycle
 * in the monitor, not silently merged into the dead record.
 *
 * Returns the new UUID so callers can surface a link to it.
 */
final class RetryDeadLetterJobAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly QueueDispatcher $queue,
        private readonly UuidGenerator $uuid,
    ) {}

    /**
     * @param  array<string|int, mixed>|null  $customPayload  When provided,
     *                                                        this payload is used instead of the stored one. Lets the user
     *                                                        edit data in the DLQ before re-dispatching.
     */
    public function __invoke(JobIdentifier $id, ?array $customPayload = null): string
    {
        $attempts = $this->repository->findAllAttempts($id);

        if (count($attempts) === 0) {
            throw new RuntimeException(sprintf('No record found for job %s.', $id->value));
        }

        $latest = $attempts[count($attempts) - 1];
        $payload = $customPayload ?? $latest->payload();

        if ($payload === null) {
            throw new RuntimeException(
                'Cannot retry: payload not stored. Enable jobs-monitor.store_payload to use DLQ retry.',
            );
        }

        $newUuid = $this->uuid->generate();
        $payload['uuid'] = $newUuid;

        $this->queue->pushRaw(
            $latest->connection,
            $latest->queue->value,
            $this->toRawPayload($latest, $payload),
        );

        return $newUuid;
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
