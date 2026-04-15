<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Enum\DurationAnomalyKind;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationAnomaly;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationBaseline;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationAnomalyModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationBaselineModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;

final class EloquentDurationBaselineRepository implements DurationBaselineRepository
{
    public function findBaseline(string $jobClass): ?DurationBaseline
    {
        $model = DurationBaselineModel::query()
            ->where('job_class', $jobClass)
            ->first();

        if ($model === null) {
            return null;
        }

        return new DurationBaseline(
            jobClass: $model->job_class,
            samplesCount: $model->samples_count,
            p50Ms: $model->p50_ms,
            p95Ms: $model->p95_ms,
            minMs: $model->min_ms,
            maxMs: $model->max_ms,
            computedOverFrom: $model->computed_over_from,
            computedOverTo: $model->computed_over_to,
        );
    }

    public function saveBaseline(DurationBaseline $baseline): void
    {
        DurationBaselineModel::query()->updateOrCreate(
            ['job_class' => $baseline->jobClass],
            [
                'samples_count' => $baseline->samplesCount,
                'p50_ms' => $baseline->p50Ms,
                'p95_ms' => $baseline->p95Ms,
                'min_ms' => $baseline->minMs,
                'max_ms' => $baseline->maxMs,
                'computed_over_from' => $baseline->computedOverFrom,
                'computed_over_to' => $baseline->computedOverTo,
            ],
        );
    }

    public function recordAnomaly(DurationAnomaly $anomaly): void
    {
        DurationAnomalyModel::query()->create([
            'job_uuid' => $anomaly->jobUuid,
            'attempt' => $anomaly->attempt,
            'job_class' => $anomaly->jobClass,
            'kind' => $anomaly->kind->value,
            'duration_ms' => $anomaly->durationMs,
            'baseline_p50_ms' => $anomaly->baselineP50Ms,
            'baseline_p95_ms' => $anomaly->baselineP95Ms,
            'samples_count' => $anomaly->samplesCount,
            'detected_at' => $anomaly->detectedAt,
        ]);
    }

    public function countAnomaliesSince(DateTimeImmutable $since): int
    {
        return DurationAnomalyModel::query()
            ->where('detected_at', '>=', $since)
            ->count();
    }

    public function jobClassesWithSamplesSince(DateTimeImmutable $since): array
    {
        return JobRecordModel::query()
            ->where('status', JobStatus::Processed->value)
            ->whereNotNull('duration_ms')
            ->where('started_at', '>=', $since)
            ->groupBy('job_class')
            ->pluck('job_class')
            ->all();
    }

    public function sampleDurationsFor(string $jobClass, DateTimeImmutable $since): array
    {
        /** @var list<int> $durations */
        $durations = JobRecordModel::query()
            ->where('status', JobStatus::Processed->value)
            ->where('job_class', $jobClass)
            ->whereNotNull('duration_ms')
            ->where('started_at', '>=', $since)
            ->pluck('duration_ms')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        return array_values($durations);
    }
}
