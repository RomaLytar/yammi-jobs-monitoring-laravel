<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\RefreshDurationBaselinesAction;

final class RefreshDurationBaselinesCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:refresh-duration-baselines
        {--lookback-days=7 : How many days of successful runs to include in the baseline}';

    /** @var string */
    protected $description = 'Recompute p50/p95 duration baselines per job class from the recent history.';

    public function handle(RefreshDurationBaselinesAction $action): int
    {
        $lookback = max(1, (int) $this->option('lookback-days'));
        $updated = $action(new DateTimeImmutable, $lookback);

        $this->info(sprintf('Refreshed baselines for %d job class(es).', $updated));

        return self::SUCCESS;
    }
}
