<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Illuminate\Database\Eloquent\Collection;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationAnomalyModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationBaselineModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;

final class DurationAnomaliesViewModel
{
    private const PER_PAGE = 50;

    public function __construct(
        /** @var Collection<int, DurationAnomalyModel> */
        public readonly Collection $recentAnomalies,
        public readonly int $anomaliesTotal,
        public readonly int $page,
        public readonly int $lastPage,
        /** @var Collection<int, DurationBaselineModel> */
        public readonly Collection $baselines,
        public readonly int $shortCount,
        public readonly int $longCount,
        /** @var array<string, JobRecordModel> Map of "uuid#attempt" → JobRecordModel for the page's anomalies. */
        public readonly array $jobRecordsByKey,
    ) {}

    public static function jobRecordKey(string $uuid, int $attempt): string
    {
        return $uuid.'#'.$attempt;
    }

    public static function build(int $page = 1): self
    {
        $page = max(1, $page);

        $total = DurationAnomalyModel::query()->count();

        $recent = DurationAnomalyModel::query()
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
            ->get();

        $baselines = DurationBaselineModel::query()
            ->orderBy('job_class')
            ->limit(200)
            ->get();

        return new self(
            recentAnomalies: $recent,
            anomaliesTotal: $total,
            page: $page,
            lastPage: (int) max(1, ceil(($total ?: 1) / self::PER_PAGE)),
            baselines: $baselines,
            shortCount: DurationAnomalyModel::query()->where('kind', 'short')->count(),
            longCount: DurationAnomalyModel::query()->where('kind', 'long')->count(),
            jobRecordsByKey: self::loadJobRecords($recent),
        );
    }

    /**
     * Bulk-fetches matching JobRecord rows for the page's anomalies in one
     * round-trip so the click-to-expand detail can show payload, exception,
     * outcome, progress, etc. without N+1 queries.
     *
     * @param  Collection<int, DurationAnomalyModel>  $anomalies
     * @return array<string, JobRecordModel>
     */
    private static function loadJobRecords(Collection $anomalies): array
    {
        if ($anomalies->isEmpty()) {
            return [];
        }

        $pairs = $anomalies->map(static fn (DurationAnomalyModel $a) => [
            'uuid' => $a->job_uuid,
            'attempt' => $a->attempt,
        ])->all();

        $query = JobRecordModel::query();
        foreach ($pairs as $i => $p) {
            if ($i === 0) {
                $query->where(function ($q) use ($p): void {
                    $q->where('uuid', $p['uuid'])->where('attempt', $p['attempt']);
                });
            } else {
                $query->orWhere(function ($q) use ($p): void {
                    $q->where('uuid', $p['uuid'])->where('attempt', $p['attempt']);
                });
            }
        }

        $map = [];
        foreach ($query->get() as $model) {
            $map[self::jobRecordKey($model->uuid, $model->attempt)] = $model;
        }

        return $map;
    }
}
