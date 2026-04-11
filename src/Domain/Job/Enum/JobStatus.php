<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Enum;

enum JobStatus: string
{
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Processed || $this === self::Failed;
    }

    public function isFailure(): bool
    {
        return $this === self::Failed;
    }

    public function isSuccess(): bool
    {
        return $this === self::Processed;
    }
}
