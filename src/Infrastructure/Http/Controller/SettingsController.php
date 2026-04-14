<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Presentation\ViewModel\Settings\SettingsIndexViewModel;

/** @internal */
final class SettingsController extends Controller
{
    public function __invoke(SettingsGate $gate, SettingsIndexViewModel $viewModel): View
    {
        $gate->authorize();

        return view('jobs-monitor::settings.index', ['vm' => $viewModel]);
    }
}
