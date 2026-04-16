<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\DetectSilentWorkersAction;

final class CheckWorkerHeartbeatsCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:heartbeats:check';

    /** @var string */
    protected $description = 'Compare observed worker heartbeats against thresholds and expectations, emitting alerts when they diverge.';

    public function handle(DetectSilentWorkersAction $action): int
    {
        $summary = $action(new DateTimeImmutable);

        $this->info(sprintf(
            'heartbeats:check → silent %d new / %d resolved, underprovisioned %d new / %d resolved.',
            $summary->silentTriggered,
            $summary->silentResolved,
            $summary->underprovisionedTriggered,
            $summary->underprovisionedResolved,
        ));

        return self::SUCCESS;
    }
}
