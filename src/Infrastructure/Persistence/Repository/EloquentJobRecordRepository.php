<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
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
                'failure_category' => $record->failureCategory()?->value,
                'payload' => $record->payload(),
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

    public function findRecent(int $limit): array
    {
        return JobRecordModel::query()
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (JobRecordModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findRecentFailures(int $hours): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        return JobRecordModel::query()
            ->where('status', JobStatus::Failed->value)
            ->where('started_at', '>=', $since)
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (JobRecordModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array{total: int, processed: int, failed: int, avg_duration_ms: float|null}
     */
    public function aggregateStatsByClass(string $jobClass): array
    {
        $query = JobRecordModel::query()->where('job_class', $jobClass);

        $total = (clone $query)->count();
        $processed = (clone $query)->where('status', JobStatus::Processed->value)->count();
        $failed = (clone $query)->where('status', JobStatus::Failed->value)->count();
        $avgDuration = (clone $query)->whereNotNull('duration_ms')->avg('duration_ms');

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'avg_duration_ms' => $avgDuration !== null ? (float) $avgDuration : null,
        ];
    }

    private const SORTABLE_COLUMNS = ['started_at', 'status', 'duration_ms', 'job_class'];

    public function findPaginated(
        ?\DateTimeImmutable $since,
        ?string $search,
        int $perPage,
        int $page,
        string $sortBy = 'started_at',
        string $sortDirection = 'desc',
        ?JobStatus $statusFilter = null,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): array {
        $query = $this->filteredQuery(
            $since,
            $search,
            $statusFilter,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'started_at';
        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($column, $direction)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn (JobRecordModel $model) => $this->toDomain($model))
            ->all();
    }

    public function countFiltered(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?JobStatus $statusFilter = null,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): int {
        return $this->filteredQuery(
            $since,
            $search,
            $statusFilter,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        )->count();
    }

    /**
     * @return array{total: int, processed: int, failed: int, processing: int}
     */
    public function statusCounts(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): array {
        $query = $this->filteredQuery(
            $since,
            $search,
            null,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        $total = (clone $query)->count();
        $processed = (clone $query)->where('status', JobStatus::Processed->value)->count();
        $failed = (clone $query)->where('status', JobStatus::Failed->value)->count();
        $processing = (clone $query)->where('status', JobStatus::Processing->value)->count();

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'processing' => $processing,
        ];
    }

    public function distinctQueues(): array
    {
        /** @var list<string> */
        return JobRecordModel::query()
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue')
            ->values()
            ->all();
    }

    public function distinctConnections(): array
    {
        /** @var list<string> */
        return JobRecordModel::query()
            ->distinct()
            ->orderBy('connection')
            ->pluck('connection')
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<JobRecordModel>
     */
    private function filteredQuery(
        ?\DateTimeImmutable $since,
        ?string $search,
        ?JobStatus $statusFilter = null,
        ?string $queueFilter = null,
        ?string $connectionFilter = null,
        ?FailureCategory $failureCategoryFilter = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = JobRecordModel::query();

        if ($since !== null) {
            $query->where('started_at', '>=', $since);
        }

        if ($search !== null && $search !== '') {
            $query->where('job_class', 'like', '%'.$search.'%');
        }

        if ($statusFilter !== null) {
            $query->where('status', $statusFilter->value);
        }

        if ($queueFilter !== null && $queueFilter !== '') {
            $query->where('queue', $queueFilter);
        }

        if ($connectionFilter !== null && $connectionFilter !== '') {
            $query->where('connection', $connectionFilter);
        }

        if ($failureCategoryFilter !== null) {
            $query->where('failure_category', $failureCategoryFilter->value);
        }

        return $query;
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return JobRecordModel::query()
            ->where('started_at', '<', $before)
            ->delete();
    }

    public function findAllAttempts(JobIdentifier $id): array
    {
        return JobRecordModel::query()
            ->where('uuid', $id->value)
            ->orderBy('attempt', 'asc')
            ->get()
            ->map(fn (JobRecordModel $model) => $this->toDomain($model))
            ->values()
            ->all();
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
            $failureCategory = is_string($model->failure_category)
                ? FailureCategory::tryFrom($model->failure_category)
                : null;
            $record->markAsFailed($model->finished_at, $model->exception ?? '', $failureCategory);
        }

        if (is_array($model->payload)) {
            $record->setPayload($model->payload);
        }

        return $record;
    }
}
