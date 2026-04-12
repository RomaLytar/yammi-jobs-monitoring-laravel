<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Presentation\ViewModel\DashboardViewModel;

/** @internal */
final class DashboardController extends Controller
{
    public function __invoke(JobsMonitorService $service): View
    {
        $viewModel = DashboardViewModel::fromService($service);

        return view('jobs-monitor::dashboard', ['vm' => $viewModel]);
    }
}
