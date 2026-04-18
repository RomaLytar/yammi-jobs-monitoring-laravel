<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Contract;

use Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport;

/**
 * Opt-in contract for queue jobs that want to give the monitor an
 * outcome report beyond the "handle() returned" boolean. Jobs returning
 * an OutcomeReport from outcome() enable the monitor to flag silent
 * successes (e.g. processed=0 where the class historically processes > 0).
 *
 * The monitor calls outcome() exactly once, after the job's handle()
 * returns successfully and before the JobProcessed listener runs.
 */
interface ReportsOutcome
{
    public function outcome(): OutcomeReport;
}
