<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console;

use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\PruneMonitorDataAction;

final class PruneMonitorDataCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:prune {--days= : Days to retain; overrides the main retention for this run}';

    /** @var string */
    protected $description = 'Delete monitor records older than the configured retention, across every historical table';

    public function handle(PruneMonitorDataAction $prune): int
    {
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption) ? (int) $daysOption : null;

        $result = ($prune)($days);

        foreach ($result->deletedByDataset as $dataset => $count) {
            $this->line(sprintf('  %-20s %d', $dataset, $count));
        }

        $total = $result->total();
        $label = $total === 1 ? 'row' : 'rows';
        $this->info("Pruned {$total} {$label} across ".count($result->deletedByDataset).' datasets.');

        return self::SUCCESS;
    }
}
