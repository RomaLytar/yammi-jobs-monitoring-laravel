<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\Contract;

use Yammi\JobsMonitor\Domain\Failure\ValueObject\NormalizedTrace;

interface TraceNormalizer
{
    public function normalize(
        string $exceptionClass,
        string $message,
        string $stackTraceAsString,
    ): NormalizedTrace;
}
