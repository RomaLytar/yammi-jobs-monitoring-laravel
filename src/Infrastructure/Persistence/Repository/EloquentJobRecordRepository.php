<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;

final class EloquentJobRecordRepository implements JobRecordRepository
{
    public function save(JobRecord $record): void
    {
        JobRecordModel::query()->updateOrCreate(
            [
                'uuid' => $record->id->value,
                'attempt' => $record->attempt->value,
            ],
            [
                'job_class' => $record->jobClass,
                'connection' => $record->connection,
                'queue' => $record->queue->value,
                'status' => $record->status()->value,
                'started_at' => $record->startedAt,
                'finished_at' => $record->finishedAt(),
                'duration_ms' => $record->duration()?->milliseconds,
                'exception' => $record->exception(),
            ],
        );
    }

    public function findByIdentifierAndAttempt(
        JobIdentifier $id,
        Attempt $attempt,
    ): ?JobRecord {
        $model = JobRecordModel::query()
            ->where('uuid', $id->value)
            ->where('attempt', $attempt->value)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(JobRecordModel $model): JobRecord
    {
        $record = new JobRecord(
            id: new JobIdentifier($model->uuid),
            attempt: new Attempt($model->attempt),
            jobClass: $model->job_class,
            connection: $model->connection,
            queue: new QueueName($model->queue),
            startedAt: $model->started_at,
        );

        $status = JobStatus::from($model->status);

        if ($status === JobStatus::Processed && $model->finished_at !== null) {
            $record->markAsProcessed($model->finished_at);
        } elseif ($status === JobStatus::Failed && $model->finished_at !== null) {
            $record->markAsFailed($model->finished_at, $model->exception ?? '');
        }

        return $record;
    }
}
