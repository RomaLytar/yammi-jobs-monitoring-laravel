<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Listener;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessed;
use Throwable;
use Yammi\JobsMonitor\Application\Action\CaptureOutcomeReportAction;
use Yammi\JobsMonitor\Domain\Job\Contract\ReportsOutcome;

/**
 * On JobProcessed, check whether the resolved job instance implements
 * ReportsOutcome. If so, capture its outcome() report and persist it
 * alongside the JobRecord so dashboards and alerts can inspect what
 * the job actually achieved beyond "handle() returned".
 *
 * Jobs that don't opt in are silently ignored.
 */
final class OutcomeReportSubscriber
{
    public function __construct(
        private readonly CaptureOutcomeReportAction $action,
    ) {}

    public function handle(JobProcessed $event): void
    {
        try {
            $instance = $this->resolveJobInstance($event);
            if (! $instance instanceof ReportsOutcome) {
                return;
            }

            ($this->action)(
                uuid: (string) $event->job->uuid(),
                attempt: $event->job->attempts(),
                report: $instance->outcome(),
            );
        } catch (Throwable $e) {
            // Outcome capture failure never breaks the queue.
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

    private function resolveJobInstance(JobProcessed $event): ?object
    {
        $payload = $event->job->payload();
        $command = $payload['data']['command'] ?? null;
        if (! is_string($command) || $command === '') {
            return null;
        }

        try {
            /** @var object $instance */
            $instance = unserialize($command);

            return $instance;
        } catch (Throwable $e) {
            return null;
        }
    }
}
