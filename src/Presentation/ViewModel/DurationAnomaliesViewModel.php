<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Illuminate\Database\Eloquent\Collection;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
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
        /** @var Collection<int, JobRecordModel> Successful jobs with suspicious outcome reports. */
        public readonly Collection $silentSuccesses,
        public readonly int $silentTotal,
        public readonly int $silentPage,
        public readonly int $silentLastPage,
        /** @var Collection<int, JobRecordModel> Failed jobs that had non-zero progress before the exception. */
        public readonly Collection $partialCompletions,
        public readonly int $partialTotal,
        public readonly int $partialPage,
        public readonly int $partialLastPage,
    ) {}

    public static function jobRecordKey(string $uuid, int $attempt): string
    {
        return $uuid.'#'.$attempt;
    }

    public static function build(int $page = 1, int $silentPage = 1, int $partialPage = 1): self
    {
        $page = max(1, $page);
        $silentPage = max(1, $silentPage);
        $partialPage = max(1, $partialPage);

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

        // Silent successes: handle() returned OK but the OutcomeReport flagged
        // it as suspicious. Three signals, any one of them counts.
        $silentQuery = JobRecordModel::query()
            ->where('status', JobStatus::Processed->value)
            ->where(function ($q): void {
                $q->whereIn('outcome_status', ['no_op', 'degraded'])
                    ->orWhere('outcome_processed', 0)
                    ->orWhere('outcome_warnings_count', '>', 0);
            });
        $silentTotal = (clone $silentQuery)->count();
        $silentRows = $silentQuery
            ->orderByDesc('finished_at')
            ->offset(($silentPage - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
            ->get();

        // Partial completions: job died after reporting non-zero progress.
        // Retrying naively will reprocess the rows it already wrote.
        $partialQuery = JobRecordModel::query()
            ->where('status', JobStatus::Failed->value)
            ->whereNotNull('progress_current')
            ->where('progress_current', '>', 0);
        $partialTotal = (clone $partialQuery)->count();
        $partialRows = $partialQuery
            ->orderByDesc('finished_at')
            ->offset(($partialPage - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
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
            silentSuccesses: $silentRows,
            silentTotal: $silentTotal,
            silentPage: $silentPage,
            silentLastPage: (int) max(1, ceil(($silentTotal ?: 1) / self::PER_PAGE)),
            partialCompletions: $partialRows,
            partialTotal: $partialTotal,
            partialPage: $partialPage,
            partialLastPage: (int) max(1, ceil(($partialTotal ?: 1) / self::PER_PAGE)),
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
