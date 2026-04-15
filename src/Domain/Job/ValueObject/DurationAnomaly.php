<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\ValueObject;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Enum\DurationAnomalyKind;

final class DurationAnomaly
{
    public function __construct(
        public readonly string $jobUuid,
        public readonly int $attempt,
        public readonly string $jobClass,
        public readonly DurationAnomalyKind $kind,
        public readonly int $durationMs,
        public readonly int $baselineP50Ms,
        public readonly int $baselineP95Ms,
        public readonly int $samplesCount,
        public readonly DateTimeImmutable $detectedAt,
    ) {}
}
