<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

final class PruneJobRecordsCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:prune {--days= : Number of days to retain}';

    /** @var string */
    protected $description = 'Remove jobs-monitor records older than the given number of days';

    public function handle(JobRecordRepository $repository): int
    {
        /** @var int $days */
        $days = $this->option('days') ?? config('jobs-monitor.retention_days', 30);

        $before = new DateTimeImmutable("-{$days} days");
        $count = $repository->deleteOlderThan($before);

        $label = $count === 1 ? 'record' : 'records';
        $this->info("Pruned {$count} {$label} older than {$days} days.");

        return self::SUCCESS;
    }
}
