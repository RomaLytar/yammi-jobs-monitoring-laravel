<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Presentation\ViewModel\DurationAnomaliesViewModel;

/** @internal */
final class DurationAnomaliesController extends Controller
{
    public function __invoke(): View
    {
        return view('jobs-monitor::anomalies', [
            'vm' => DurationAnomaliesViewModel::build(),
        ]);
    }
}
