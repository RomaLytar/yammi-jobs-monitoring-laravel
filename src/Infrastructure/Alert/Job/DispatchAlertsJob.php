<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Job;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yammi\JobsMonitor\Application\Action\EvaluateAlertRulesAction;

/**
 * Scheduler-dispatched job that runs one pass of the alert orchestrator.
 *
 * Queued by design — notification delivery must not block the host
 * worker processing the job that just failed. The scheduler enqueues
 * this job once per minute; the host's own queue worker picks it up.
 *
 * @internal
 */
final class DispatchAlertsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(EvaluateAlertRulesAction $action): void
    {
        $action(new DateTimeImmutable);
    }
}
