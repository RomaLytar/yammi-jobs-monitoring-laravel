<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationAnomaly;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationBaseline;

final class InMemoryDurationBaselineRepository implements DurationBaselineRepository
{
    /** @var array<string, DurationBaseline> */
    private array $baselines = [];

    /** @var list<DurationAnomaly> */
    private array $anomalies = [];

    public function findBaseline(string $jobClass): ?DurationBaseline
    {
        return $this->baselines[$jobClass] ?? null;
    }

    public function saveBaseline(DurationBaseline $baseline): void
    {
        $this->baselines[$baseline->jobClass] = $baseline;
    }

    public function recordAnomaly(DurationAnomaly $anomaly): void
    {
        $this->anomalies[] = $anomaly;
    }

    public function countAnomaliesSince(DateTimeImmutable $since): int
    {
        return count(array_filter(
            $this->anomalies,
            static fn (DurationAnomaly $a) => $a->detectedAt >= $since,
        ));
    }

    public function jobClassesWithSamplesSince(DateTimeImmutable $since): array
    {
        return [];
    }

    public function sampleDurationsFor(string $jobClass, DateTimeImmutable $since): array
    {
        return [];
    }

    /**
     * @return list<DurationAnomaly>
     */
    public function allAnomalies(): array
    {
        return $this->anomalies;
    }
}
