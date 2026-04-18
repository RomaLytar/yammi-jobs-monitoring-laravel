<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Enum;

enum DurationAnomalyKind: string
{
    /** Ran much faster than historical baseline — likely silent no-op. */
    case Short = 'short';

    /** Ran much slower than historical baseline — likely stuck / degraded. */
    case Long = 'long';

    public function label(): string
    {
        return match ($this) {
            self::Short => 'Unexpectedly fast',
            self::Long => 'Unexpectedly slow',
        };
    }
}
