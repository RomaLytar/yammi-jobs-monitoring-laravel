<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\DashboardViewModel;

/** @internal */
final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        JobRecordRepository $repository,
        PayloadRedactor $redactor,
        ConfigRepository $config,
    ): View {
        $period = $this->str($request, 'period', '24h');
        $search = $this->str($request, 'search', '');

        $viewModel = DashboardViewModel::fromRepository(
            $repository,
            $period,
            $search,
            [
                'page' => max(1, (int) $request->query('page', '1')),
                'sort' => $this->str($request, 'sort', 'started_at'),
                'dir' => $this->str($request, 'dir', 'desc'),
                'fpage' => max(1, (int) $request->query('fpage', '1')),
                'fsort' => $this->str($request, 'fsort', 'started_at'),
                'fdir' => $this->str($request, 'fdir', 'desc'),
                'status' => $this->str($request, 'status', ''),
                'queue' => $this->str($request, 'queue', ''),
                'connection' => $this->str($request, 'connection', ''),
                'failure_category' => $this->str($request, 'failure_category', ''),
            ],
            $redactor,
            (bool) $config->get('jobs-monitor.store_payload', false),
        );

        return view('jobs-monitor::dashboard', ['vm' => $viewModel]);
    }

    private function str(Request $request, string $key, string $default): string
    {
        $value = $request->query($key, $default);

        return is_string($value) ? $value : $default;
    }
}
