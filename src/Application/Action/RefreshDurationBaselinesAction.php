<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateInterval;
use DateTimeImmutable;
use Yammi\JobsMonitor\Application\Service\PercentileCalculator;
use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationBaseline;

/**
 * Rebuilds the p50/p95 duration baseline for every job class with
 * successful runs in the configured lookback window. Run on a schedule
 * (default: hourly). Detection at job-processed time reads the most
 * recent baseline computed by this action.
 *
 * Only classes with strictly MORE than {@see MIN_SAMPLES_FOR_BASELINE}
 * successful runs in the window get a baseline — fewer samples produce
 * a noisy percentile (a job that ran 3 times has p50 = its middle one;
 * the fast next run would falsely trip "short anomaly").
 */
final class RefreshDurationBaselinesAction
{
    /** Minimum the count must exceed (strictly): so count > 3 is required. */
    public const MIN_SAMPLES_FOR_BASELINE = 3;

    public function __construct(
        private readonly DurationBaselineRepository $repository,
        private readonly PercentileCalculator $percentiles,
    ) {}

    public function __invoke(DateTimeImmutable $now, int $lookbackDays = 7): int
    {
        $since = $now->sub(new DateInterval('P'.max(1, $lookbackDays).'D'));

        $updated = 0;
        foreach ($this->repository->jobClassesWithSamplesSince($since) as $jobClass) {
            $samples = $this->repository->sampleDurationsFor($jobClass, $since);
            if (count($samples) <= self::MIN_SAMPLES_FOR_BASELINE) {
                continue;
            }

            $baseline = new DurationBaseline(
                jobClass: $jobClass,
                samplesCount: count($samples),
                p50Ms: $this->percentiles->compute($samples, 50),
                p95Ms: $this->percentiles->compute($samples, 95),
                minMs: min($samples),
                maxMs: max($samples),
                computedOverFrom: $since,
                computedOverTo: $now,
            );

            $this->repository->saveBaseline($baseline);
            $updated++;
        }

        return $updated;
    }
}
