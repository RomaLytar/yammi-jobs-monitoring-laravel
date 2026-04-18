<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\DTO\ScheduledTaskRunData;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;

/**
 * Upserts a scheduled-task run identified by (mutex, startedAt). Called
 * once on task start (status Running) and once on task completion
 * (Success/Failed/Skipped) with the same startedAt — the second call
 * transitions the existing record to its terminal state.
 */
final class RecordScheduledTaskRunAction
{
    public function __construct(
        private readonly ScheduledTaskRunRepository $repository,
    ) {}

    public function __invoke(ScheduledTaskRunData $data): void
    {
        $existing = $this->repository->findRunning($data->mutex, $data->startedAt);

        $run = $existing ?? new ScheduledTaskRun(
            mutex: $data->mutex,
            taskName: $data->taskName,
            expression: $data->expression,
            timezone: $data->timezone,
            startedAt: $data->startedAt,
            host: $data->host,
            command: $data->command,
        );

        // Terminal transitions need a finishedAt; fall back to startedAt
        // if the caller did not supply one (unusual but possible when an
        // event arrives without a matching start).
        $finishedAt = $data->finishedAt ?? $data->startedAt;

        match ($data->status) {
            ScheduledTaskStatus::Running => null,
            ScheduledTaskStatus::Success => $run->markAsSucceeded($finishedAt, $data->exitCode, $data->output),
            ScheduledTaskStatus::Failed => $run->markAsFailed($finishedAt, $data->exception, $data->exitCode, $data->output),
            ScheduledTaskStatus::Skipped => $run->markAsSkipped($finishedAt, $data->output),
            ScheduledTaskStatus::Late => $run->markAsLate($finishedAt),
        };

        $this->repository->save($run);
    }
}
