<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Enum;

enum OutcomeStatus: string
{
    /** Job did useful work and reported healthy outcome. */
    case Ok = 'ok';

    /** Job completed with no work performed (zero processed). Suspicious. */
    case NoOp = 'no_op';

    /** Job completed but with non-fatal warnings the host wants visible. */
    case Degraded = 'degraded';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Ok',
            self::NoOp => 'No-op',
            self::Degraded => 'Degraded',
        };
    }
}
