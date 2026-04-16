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
    public function __invoke(Request $request): View
    {
        return view('jobs-monitor::anomalies', [
            'vm' => DurationAnomaliesViewModel::build(
                page: max(1, (int) $request->query('page', '1')),
                silentPage: max(1, (int) $request->query('spage', '1')),
                partialPage: max(1, (int) $request->query('ppage', '1')),
            ),
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
                'refresh' => sprintf('Refresh failed: %s', $e::class),
            ]);
        }
    }
}
