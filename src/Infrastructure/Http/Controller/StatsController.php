<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\StatsViewModel;

/** @internal */
final class StatsController extends Controller
{
    public function __invoke(Request $request, JobRecordRepository $repository): View
    {
        $period = $this->str($request, 'period', '24h');

        $viewModel = StatsViewModel::fromRepository($repository, $period);

        return view('jobs-monitor::stats', ['vm' => $viewModel]);
    }

    private function str(Request $request, string $key, string $default): string
    {
        $value = $request->query($key, $default);

        return is_string($value) ? $value : $default;
    }
}
