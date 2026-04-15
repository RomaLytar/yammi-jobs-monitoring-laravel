<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Illuminate\Database\Eloquent\Collection;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationAnomalyModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationBaselineModel;

final class DurationAnomaliesViewModel
{
    public function __construct(
        /** @var Collection<int, DurationAnomalyModel> */
        public readonly Collection $recentAnomalies,
        /** @var Collection<int, DurationBaselineModel> */
        public readonly Collection $baselines,
        public readonly int $shortCount,
        public readonly int $longCount,
    ) {}

    public static function build(): self
    {
        $recent = DurationAnomalyModel::query()
            ->orderByDesc('detected_at')
            ->limit(100)
            ->get();

        $baselines = DurationBaselineModel::query()
            ->orderBy('job_class')
            ->limit(200)
            ->get();

        return new self(
            recentAnomalies: $recent,
            baselines: $baselines,
            shortCount: DurationAnomalyModel::query()->where('kind', 'short')->count(),
            longCount: DurationAnomalyModel::query()->where('kind', 'long')->count(),
        );
    }
}
