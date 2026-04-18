<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\GetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\ResetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\UpdateGeneralSettingsAction;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Http\Request\UpdateGeneralSettingsRequest;

/** @internal */
final class GeneralSettingsController extends Controller
{
    public function index(SettingsGate $gate, GetGeneralSettingsAction $get): View
    {
        $gate->authorize();

        return view('jobs-monitor::settings.general.index', [
            'groups' => $get(),
        ]);
    }

    public function update(
        SettingsGate $gate,
        UpdateGeneralSettingsRequest $request,
        UpdateGeneralSettingsAction $update,
    ): RedirectResponse {
        $gate->authorize();

        $update($request->settings());

        return redirect()
            ->route('jobs-monitor.settings.general')
            ->with('jobs_monitor_status', 'Settings saved.');
    }

    public function reset(SettingsGate $gate, ResetGeneralSettingsAction $resetAction): RedirectResponse
    {
        $gate->authorize();

        $resetAction();

        return redirect()
            ->route('jobs-monitor.settings.general')
            ->with('jobs_monitor_status', 'All settings reset to package defaults.');
    }
}
