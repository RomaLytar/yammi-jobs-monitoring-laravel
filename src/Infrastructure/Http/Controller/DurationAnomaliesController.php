<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;
use Yammi\JobsMonitor\Application\Action\RefreshDurationBaselinesAction;
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

    public function refreshBaselines(Request $request, RefreshDurationBaselinesAction $action): RedirectResponse
    {
        $lookback = max(1, min(90, (int) $request->input('lookback_days', 7)));

        try {
            $updated = $action(new DateTimeImmutable, $lookback);

            return back()->with('status', sprintf(
                'Recomputed baselines for %d job class(es) over the last %d day(s).',
                $updated,
                $lookback,
            ));
        } catch (Throwable $e) {
            return back()->withErrors([
                'refresh' => sprintf('Refresh failed: %s', $e->getMessage()),
            ]);
        }
    }
}
