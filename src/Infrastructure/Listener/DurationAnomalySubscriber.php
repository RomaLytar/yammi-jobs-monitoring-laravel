<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Listener;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessed;
use Throwable;
use Yammi\JobsMonitor\Application\Action\DetectDurationAnomalyAction;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * On every successful job completion, compare the just-finished duration
 * against the stored baseline for its class and record an anomaly when
 * the deviation crosses the configured factors. Fully passive — no
 * changes required on the host's job code.
 *
 * We read the persisted JobRecord rather than re-computing the duration
 * here so the single source of truth for "what happened on this run"
 * stays the JobRecord aggregate.
 */
final class DurationAnomalySubscriber
{
    public function __construct(
        private readonly DetectDurationAnomalyAction $detector,
        private readonly JobRecordRepository $records,
    ) {}

    public function handle(JobProcessed $event): void
    {
        try {
            $uuid = (string) $event->job->uuid();
            $attempt = $event->job->attempts();

            $record = $this->records->findByIdentifierAndAttempt(
                new JobIdentifier($uuid),
                new Attempt($attempt),
            );

            if ($record === null || $record->duration() === null) {
                return;
            }

            ($this->detector)(
                jobUuid: $uuid,
                attempt: $attempt,
                jobClass: $record->jobClass,
                durationMs: $record->duration()->milliseconds,
                detectedAt: new DateTimeImmutable,
            );
        } catch (Throwable $e) {
            // Observability must never break the queue worker.
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobProcessed::class => 'handle',
        ];
    }
}
