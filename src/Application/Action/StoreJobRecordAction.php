<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\DTO\JobRecordData;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;

/**
 * Single use case responsible for translating an incoming
 * job lifecycle observation into a stored JobRecord.
 *
 * Idempotent against the (id, attempt) pair: receiving the same
 * stage twice never duplicates a record. Re-marking a terminal
 * record is silently ignored — the domain entity throws on the
 * transition, but the action treats that as a no-op because the
 * record is already in the desired terminal state.
 */
final class StoreJobRecordAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
    ) {}

    public function __invoke(JobRecordData $data): void
    {
        $id = new JobIdentifier($data->id);
        $attempt = new Attempt($data->attempt);

        $record = $this->repository->findByIdentifierAndAttempt($id, $attempt)
            ?? new JobRecord(
                id: $id,
                attempt: $attempt,
                jobClass: $data->jobClass,
                connection: $data->connection,
                queue: new QueueName($data->queue),
                startedAt: $data->startedAt,
            );

        if ($data->payload !== null) {
            $record->setPayload($data->payload);
        }

        $this->applyTransition($record, $data);

        $this->repository->save($record);
    }

    private function applyTransition(JobRecord $record, JobRecordData $data): void
    {
        if ($record->status()->isTerminal()) {
            // Already in a terminal state — nothing to apply.
            return;
        }

        if ($data->status === JobStatus::Processed && $data->finishedAt !== null) {
            $record->markAsProcessed($data->finishedAt);

            return;
        }

        if ($data->status === JobStatus::Failed && $data->finishedAt !== null) {
            $record->markAsFailed($data->finishedAt, $data->exception ?? '');
        }
    }
}
