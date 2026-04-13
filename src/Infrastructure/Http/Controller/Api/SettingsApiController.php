<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings\SettingsIndexResource;
use Yammi\JobsMonitor\Presentation\ViewModel\Settings\SettingsIndexViewModel;

/** @internal */
final class SettingsApiController extends Controller
{
    public function __invoke(SettingsGate $gate, SettingsIndexViewModel $viewModel): JsonResource
    {
        $gate->authorize();

        return new SettingsIndexResource($viewModel);
    }
}
