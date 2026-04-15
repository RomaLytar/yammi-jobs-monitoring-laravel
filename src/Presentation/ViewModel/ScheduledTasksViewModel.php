<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\ScheduledTaskRunModel;

final class ScheduledTasksViewModel
{
    /**
     * @param  array<string, ScheduledTaskRun>  $latestPerMutex
     * @param  list<ScheduledTaskRun>  $recentRuns
     * @param  array<string, int>  $statusCounts
     */
    public function __construct(
        public readonly array $latestPerMutex,
        public readonly array $recentRuns,
        public readonly array $statusCounts,
    ) {}

    public static function fromRepository(ScheduledTaskRunRepository $repository): self
    {
        $latest = $repository->latestRunPerMutex();

        $recentRows = ScheduledTaskRunModel::query()
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        $recent = [];
        foreach ($recentRows as $model) {
            $recent[] = self::modelToDomain($model);
        }

        $statusCounts = [];
        foreach (ScheduledTaskStatus::cases() as $case) {
            $statusCounts[$case->value] = ScheduledTaskRunModel::query()
                ->where('status', $case->value)
                ->count();
        }

        return new self(
            latestPerMutex: $latest,
            recentRuns: $recent,
            statusCounts: $statusCounts,
        );
    }

    private static function modelToDomain(ScheduledTaskRunModel $model): ScheduledTaskRun
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

        match ($status) {
            ScheduledTaskStatus::Success => $run->markAsSucceeded($finishedAt, $model->exit_code, $model->output),
            ScheduledTaskStatus::Failed => $run->markAsFailed($finishedAt, $model->exception, $model->exit_code, $model->output),
            ScheduledTaskStatus::Skipped => $run->markAsSkipped($finishedAt, $model->output),
            ScheduledTaskStatus::Late => $run->markAsLate($finishedAt),
            ScheduledTaskStatus::Running => null,
        };

        return $run;
    }
}
