<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\ValueObject;

use InvalidArgumentException;
use Yammi\JobsMonitor\Domain\Job\Enum\OutcomeStatus;

/**
 * Captures "what did the job actually achieve" beyond the boolean
 * handle()-threw-or-not signal the queue gives us.
 *
 * Jobs return this from outcome() when they implement the ReportsOutcome
 * contract. The monitor stores it alongside the record and can alert
 * on suspicious values (e.g. zero processed when the class usually
 * processes > 0).
 */
final class OutcomeReport
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $processed,
        public readonly int $skipped,
        public readonly array $warnings,
        public readonly OutcomeStatus $status,
    ) {
        if ($processed < 0) {
            throw new InvalidArgumentException('Processed count cannot be negative.');
        }
        if ($skipped < 0) {
            throw new InvalidArgumentException('Skipped count cannot be negative.');
        }
    }
}
