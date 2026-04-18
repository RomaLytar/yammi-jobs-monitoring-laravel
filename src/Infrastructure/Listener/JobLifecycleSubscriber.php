<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Listener;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
<<<<<<< HEAD
use Throwable;
use Yammi\JobsMonitor\Application\Action\RecordFailureFingerprintAction;
=======
>>>>>>> origin/main
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
<<<<<<< HEAD
        private readonly RecordFailureFingerprintAction $fingerprintAction,
=======
>>>>>>> origin/main
        private readonly PayloadRedactor $redactor,
        private readonly bool $storePayload,
    ) {}

    public function handleJobProcessing(JobProcessing $event): void
    {
<<<<<<< HEAD
        if ($this->isInternalJob($event->job->resolveName())) {
            return;
        }

=======
>>>>>>> origin/main
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
<<<<<<< HEAD
        if ($this->isInternalJob($event->job->resolveName())) {
            return;
        }

=======
>>>>>>> origin/main
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
<<<<<<< HEAD
        if ($this->isInternalJob($event->job->resolveName())) {
            return;
        }

=======
>>>>>>> origin/main
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
     * Fires for every exception thrown by a job — including the intermediate
     * ones where the job still has retries left. Without this hook those
     * attempts would remain stuck in the Processing state, hiding their
     * exception from the retry timeline.
     */
    public function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
<<<<<<< HEAD
        if ($this->isInternalJob($event->job->resolveName())) {
            return;
        }

=======
>>>>>>> origin/main
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
<<<<<<< HEAD

        $this->recordFingerprint((string) $event->job->uuid(), $event->job->attempts(), $event->job->resolveName(), $event->exception, $now);
    }

    private function isInternalJob(string $jobClass): bool
    {
        return str_starts_with($jobClass, 'Yammi\\JobsMonitor\\');
    }

    /**
     * Fingerprinting is side-effect only; a failure here must never
     * escape and break the host's job processing. The host app's queue
     * worker is sacred.
     */
    private function recordFingerprint(
        string $uuid,
        int $attempt,
        string $jobClass,
        Throwable $exception,
        DateTimeImmutable $occurredAt,
    ): void {
        try {
            ($this->fingerprintAction)(
                id: $uuid,
                attempt: $attempt,
                jobClass: $jobClass,
                exception: $exception,
                occurredAt: $occurredAt,
            );
        } catch (Throwable $e) {
            // Fingerprinting is observability, not correctness. Swallow
            // and move on so the underlying failure is still recorded.
        }
=======
>>>>>>> origin/main
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
            JobExceptionOccurred::class => 'handleJobExceptionOccurred',
        ];
    }
}
