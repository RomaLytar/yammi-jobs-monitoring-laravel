<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationAnomaly;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationBaseline;

interface DurationBaselineRepository
{
    public function findBaseline(string $jobClass): ?DurationBaseline;

    public function saveBaseline(DurationBaseline $baseline): void;

    public function recordAnomaly(DurationAnomaly $anomaly): void;

    public function countAnomaliesSince(DateTimeImmutable $since): int;

    /**
     * Returns the list of job classes that have at least one successful
     * JobRecord in the given window — used by the baseline-refresh job
     * to decide which classes to recompute.
     *
     * @return list<string>
     */
    public function jobClassesWithSamplesSince(DateTimeImmutable $since): array;

    /**
     * Returns successful job durations (in ms) for a class within the
     * window, for percentile computation.
     *
     * @return list<int>
     */
    public function sampleDurationsFor(string $jobClass, DateTimeImmutable $since): array;
}
