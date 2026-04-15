<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\DetectLateScheduledTasksAction;

final class DetectLateScheduledTasksCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:scheduled-scan
        {--tolerance=30 : Minutes a run may stay in Running before being flagged Late}';

    /** @var string */
    protected $description = 'Flag scheduled-task runs that never finished within the expected window.';

    public function handle(DetectLateScheduledTasksAction $action): int
    {
        $tolerance = max(1, (int) $this->option('tolerance'));
        $flagged = $action(new DateTimeImmutable, $tolerance);

        $this->info(sprintf('Flagged %d stuck scheduled-task run(s) as late.', $flagged));

        return self::SUCCESS;
    }
}
