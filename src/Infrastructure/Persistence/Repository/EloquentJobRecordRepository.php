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

    public function aggregateStatsByClassMulti(?\DateTimeImmutable $since): array
    {
        $query = JobRecordModel::query();

        if ($since !== null) {
            $query->where('started_at', '>=', $since);
        }

        /** @var list<array{
         *     job_class: string,
         *     total: int,
         *     processed: int,
         *     failed: int,
         *     avg_duration_ms: float|null,
         *     max_duration_ms: int|null,
         *     retry_count: int
         * }> */
        return $query
            ->selectRaw('job_class')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(sprintf('SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as processed', "'".JobStatus::Processed->value."'"))
            ->selectRaw(sprintf('SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as failed', "'".JobStatus::Failed->value."'"))
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->selectRaw('MAX(duration_ms) as max_duration_ms')
            ->selectRaw('SUM(CASE WHEN attempt > 1 THEN 1 ELSE 0 END) as retry_count')
            ->groupBy('job_class')
            ->orderByDesc('total')
            ->get()
            ->map(static function (JobRecordModel $row): array {
                $attrs = $row->getAttributes();

                return [
                    'job_class' => (string) $attrs['job_class'],
                    'total' => (int) $attrs['total'],
                    'processed' => (int) $attrs['processed'],
                    'failed' => (int) $attrs['failed'],
                    'avg_duration_ms' => $attrs['avg_duration_ms'] !== null ? (float) $attrs['avg_duration_ms'] : null,
                    'max_duration_ms' => $attrs['max_duration_ms'] !== null ? (int) $attrs['max_duration_ms'] : null,
                    'retry_count' => (int) $attrs['retry_count'],
                ];
            })
            ->values()
            ->all();
    }

    public function findDeadLetterJobs(int $perPage, int $page, int $maxTries): array
    {
        $models = $this->deadLetterQuery($maxTries)
            ->orderByDesc('started_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return $models
            ->map(fn (JobRecordModel $m) => $this->toDomain($m))
            ->values()
            ->all();
    }

    public function countDeadLetterJobs(int $maxTries): int
    {
        return $this->deadLetterQuery($maxTries)->count();
    }

    public function deleteByIdentifier(JobIdentifier $id): int
    {
        return JobRecordModel::query()->where('uuid', $id->value)->delete();
    }

    public function countFailuresSince(\DateTimeImmutable $since, ?int $minAttempt = null): int
    {
        return $this->failureWindowQuery($since, $minAttempt)->count();
    }

    public function countFailuresByCategorySince(
        FailureCategory $category,
        \DateTimeImmutable $since,
        ?int $minAttempt = null,
    ): int {
        return $this->failureWindowQuery($since, $minAttempt)
            ->where('failure_category', $category->value)
            ->count();
    }

    public function countFailuresByClassSince(
        string $jobClass,
        \DateTimeImmutable $since,
        ?int $minAttempt = null,
    ): int {
        return $this->failureWindowQuery($since, $minAttempt)
            ->where('job_class', $jobClass)
            ->count();
    }

    public function findFailureSamples(
        \DateTimeImmutable $since,
        int $limit,
        ?int $minAttempt = null,
        ?FailureCategory $category = null,
        ?string $jobClass = null,
    ): array {
        $query = $this->failureWindowQuery($since, $minAttempt);

        if ($category !== null) {
            $query->where('failure_category', $category->value);
        }

        if ($jobClass !== null) {
            $query->where('job_class', $jobClass);
        }

        return $query
            ->orderByDesc('finished_at')
            ->limit($limit)
            ->get()
            ->map(fn (JobRecordModel $model) => $this->toDomain($model))
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<JobRecordModel>
     */
    private function failureWindowQuery(\DateTimeImmutable $since, ?int $minAttempt): \Illuminate\Database\Eloquent\Builder
    {
        $query = JobRecordModel::query()
            ->where('status', JobStatus::Failed->value)
            ->where('finished_at', '>=', $since);

        if ($minAttempt !== null) {
            $query->where('attempt', '>=', $minAttempt);
        }

        return $query;
    }

    /**
     * A UUID is "dead" when its highest-attempt row is Failed AND either
     * the failure_category is permanent/critical OR the attempt number
     * has reached $maxTries.
     *
     * @return \Illuminate\Database\Eloquent\Builder<JobRecordModel>
     */
    private function deadLetterQuery(int $maxTries): \Illuminate\Database\Eloquent\Builder
    {
        // Rank attempts per UUID so only the latest survives. Using a
        // subquery keeps this portable across sqlite/mysql/postgres.
        $latestPerUuid = JobRecordModel::query()
            ->selectRaw('uuid, MAX(attempt) as max_attempt')
            ->groupBy('uuid');

        return JobRecordModel::query()
            ->joinSub($latestPerUuid, 'latest', function ($join): void {
                $join->on('jobs_monitor.uuid', '=', 'latest.uuid')
                    ->on('jobs_monitor.attempt', '=', 'latest.max_attempt');
            })
            ->where('jobs_monitor.status', JobStatus::Failed->value)
            ->where(function ($q) use ($maxTries): void {
                $q->whereIn('jobs_monitor.failure_category', [
                    FailureCategory::Permanent->value,
                    FailureCategory::Critical->value,
                ])->orWhere('jobs_monitor.attempt', '>=', $maxTries);
            })
            ->select('jobs_monitor.*');
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
