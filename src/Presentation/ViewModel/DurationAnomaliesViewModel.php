<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Illuminate\Database\Eloquent\Collection;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationAnomalyModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationBaselineModel;

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
    ) {}

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
        );
    }
}
