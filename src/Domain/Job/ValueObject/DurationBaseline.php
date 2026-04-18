<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\ValueObject;

use DateTimeImmutable;

final class DurationBaseline
{
    public function __construct(
        public readonly string $jobClass,
        public readonly int $samplesCount,
        public readonly int $p50Ms,
        public readonly int $p95Ms,
        public readonly int $minMs,
        public readonly int $maxMs,
        public readonly DateTimeImmutable $computedOverFrom,
        public readonly DateTimeImmutable $computedOverTo,
    ) {}
}
