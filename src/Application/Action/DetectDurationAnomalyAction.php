<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Enum\DurationAnomalyKind;
use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationAnomaly;

/**
 * Compares a just-completed job's duration against the stored baseline
 * for its class. Records a DurationAnomaly event when the deviation
 * crosses the configured short/long factors. No-op for classes without
 * enough samples in the baseline.
 */
final class DetectDurationAnomalyAction
{
    public function __construct(
        private readonly DurationBaselineRepository $repository,
        private readonly int $minSamples,
        private readonly float $shortFactor,
        private readonly float $longFactor,
    ) {}

    public function __invoke(
        string $jobUuid,
        int $attempt,
        string $jobClass,
        int $durationMs,
        DateTimeImmutable $detectedAt,
    ): ?DurationAnomaly {
        $baseline = $this->repository->findBaseline($jobClass);
        if ($baseline === null || $baseline->samplesCount < $this->minSamples) {
            return null;
        }

        $kind = $this->classify($durationMs, $baseline->p50Ms, $baseline->p95Ms);
        if ($kind === null) {
            return null;
        }

        $anomaly = new DurationAnomaly(
            jobUuid: $jobUuid,
            attempt: $attempt,
            jobClass: $jobClass,
            kind: $kind,
            durationMs: $durationMs,
            baselineP50Ms: $baseline->p50Ms,
            baselineP95Ms: $baseline->p95Ms,
            samplesCount: $baseline->samplesCount,
            detectedAt: $detectedAt,
        );

        $this->repository->recordAnomaly($anomaly);

        return $anomaly;
    }

    private function classify(int $duration, int $p50, int $p95): ?DurationAnomalyKind
    {
        if ($p50 > 0 && $duration < (int) floor($p50 * $this->shortFactor)) {
            return DurationAnomalyKind::Short;
        }

        if ($p95 > 0 && $duration > (int) ceil($p95 * $this->longFactor)) {
            return DurationAnomalyKind::Long;
        }

        return null;
    }
}
