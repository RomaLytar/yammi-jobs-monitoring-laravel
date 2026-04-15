<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\ScheduledTaskRunModel;

final class EloquentScheduledTaskRunRepository implements ScheduledTaskRunRepository
{
    public function save(ScheduledTaskRun $run): void
    {
        ScheduledTaskRunModel::query()->updateOrCreate(
            [
                'mutex' => $run->mutex,
                'started_at' => $run->startedAt,
            ],
            [
                'task_name' => $run->taskName,
                'expression' => $run->expression,
                'timezone' => $run->timezone,
                'status' => $run->status()->value,
                'finished_at' => $run->finishedAt(),
                'duration_ms' => $run->duration()?->milliseconds,
                'exit_code' => $run->exitCode(),
                'output' => $run->output(),
                'exception' => $run->exception(),
                'host' => $run->host,
            ],
        );
    }

    public function findRunning(string $mutex, DateTimeImmutable $startedAt): ?ScheduledTaskRun
    {
        $model = ScheduledTaskRunModel::query()
            ->where('mutex', $mutex)
            ->where('started_at', $startedAt)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findStuckRunning(DateTimeImmutable $olderThan): iterable
    {
        return ScheduledTaskRunModel::query()
            ->where('status', ScheduledTaskStatus::Running->value)
            ->where('started_at', '<', $olderThan)
            ->get()
            ->map(fn (ScheduledTaskRunModel $model) => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function countFailedSince(DateTimeImmutable $since): int
    {
        return ScheduledTaskRunModel::query()
            ->where('status', ScheduledTaskStatus::Failed->value)
            ->where('started_at', '>=', $since)
            ->count();
    }

    public function countLateSince(DateTimeImmutable $since): int
    {
        return ScheduledTaskRunModel::query()
            ->where('status', ScheduledTaskStatus::Late->value)
            ->where('started_at', '>=', $since)
            ->count();
    }

    public function findPaginated(int $perPage, int $page, array $filters): array
    {
        $allowedSort = ['started_at', 'duration_ms', 'task_name', 'status'];
        $sort = in_array($filters['sort'] ?? 'started_at', $allowedSort, true)
            ? ($filters['sort'] ?? 'started_at')
            : 'started_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = ScheduledTaskRunModel::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('task_name', 'like', $term)
                    ->orWhere('mutex', 'like', $term)
                    ->orWhere('expression', 'like', $term);
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get()
            ->map(fn (ScheduledTaskRunModel $model) => $this->toDomain($model))
            ->values()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public function statusCounts(): array
    {
        $counts = [];
        foreach (ScheduledTaskStatus::cases() as $case) {
            $counts[$case->value] = 0;
        }

        $rows = ScheduledTaskRunModel::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->c;
        }

        return $counts;
    }

    public function latestRunPerMutex(): array
    {
        $subquery = ScheduledTaskRunModel::query()
            ->selectRaw('mutex, MAX(started_at) as latest_started_at')
            ->groupBy('mutex');

        $rows = ScheduledTaskRunModel::query()
            ->joinSub($subquery, 'latest', function ($join): void {
                $join->on('jobs_monitor_scheduled_runs.mutex', '=', 'latest.mutex')
                    ->on('jobs_monitor_scheduled_runs.started_at', '=', 'latest.latest_started_at');
            })
            ->select('jobs_monitor_scheduled_runs.*')
            ->get();

        $result = [];
        foreach ($rows as $model) {
            $result[$model->mutex] = $this->toDomain($model);
        }

        return $result;
    }

    private function toDomain(ScheduledTaskRunModel $model): ScheduledTaskRun
    {
        $run = new ScheduledTaskRun(
            mutex: $model->mutex,
            taskName: $model->task_name,
            expression: $model->expression,
            timezone: $model->timezone,
            startedAt: $model->started_at,
            host: $model->host,
        );

        $status = ScheduledTaskStatus::from($model->status);
        $finishedAt = $model->finished_at;

        switch ($status) {
            case ScheduledTaskStatus::Success:
                $run->markAsSucceeded($finishedAt, $model->exit_code, $model->output);
                break;
            case ScheduledTaskStatus::Failed:
                $run->markAsFailed($finishedAt, $model->exception, $model->exit_code, $model->output);
                break;
            case ScheduledTaskStatus::Skipped:
                $run->markAsSkipped($finishedAt, $model->output);
                break;
            case ScheduledTaskStatus::Late:
                $run->markAsLate($finishedAt);
                break;
            case ScheduledTaskStatus::Running:
                break;
        }

        return $run;
    }
}
