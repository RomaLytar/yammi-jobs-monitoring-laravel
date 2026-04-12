<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Listener;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Yammi\JobsMonitor\Application\Action\StoreJobRecordAction;
use Yammi\JobsMonitor\Application\DTO\JobRecordData;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;

/**
 * Bridge from Laravel queue events to the Application layer.
 *
 * The subscriber is the only place in the package that touches the
 * `Illuminate\Queue\Events\*` types — it flattens them into a plain
 * JobRecordData DTO and hands them to StoreJobRecordAction. The
 * Application and Domain layers stay framework-agnostic.
 */
final class JobLifecycleSubscriber
{
    public function __construct(
        private readonly StoreJobRecordAction $action,
        private readonly PayloadRedactor $redactor,
        private readonly bool $storePayload,
    ) {}

    public function handleJobProcessing(JobProcessing $event): void
    {
        $now = new DateTimeImmutable;

        ($this->action)(new JobRecordData(
            id: (string) $event->job->uuid(),
            attempt: $event->job->attempts(),
            jobClass: $event->job->resolveName(),
            connection: $event->connectionName,
            queue: $event->job->getQueue() ?: 'default',
            status: JobStatus::Processing,
            startedAt: $now,
            payload: $this->storePayload ? $this->redactor->redact($event->job->payload()) : null,
        ));
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $now = new DateTimeImmutable;

        ($this->action)(new JobRecordData(
            id: (string) $event->job->uuid(),
            attempt: $event->job->attempts(),
            jobClass: $event->job->resolveName(),
            connection: $event->connectionName,
            queue: $event->job->getQueue() ?: 'default',
            status: JobStatus::Processed,
            startedAt: $now,
            finishedAt: $now,
        ));
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $now = new DateTimeImmutable;

        ($this->action)(new JobRecordData(
            id: (string) $event->job->uuid(),
            attempt: $event->job->attempts(),
            jobClass: $event->job->resolveName(),
            connection: $event->connectionName,
            queue: $event->job->getQueue() ?: 'default',
            status: JobStatus::Failed,
            startedAt: $now,
            finishedAt: $now,
            exception: sprintf(
                '%s: %s',
                $event->exception::class,
                $event->exception->getMessage(),
            ),
        ));
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobProcessing::class => 'handleJobProcessing',
            JobProcessed::class => 'handleJobProcessed',
            JobFailed::class => 'handleJobFailed',
        ];
    }
}
