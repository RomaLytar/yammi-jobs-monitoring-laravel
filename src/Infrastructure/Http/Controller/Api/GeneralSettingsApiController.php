<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\GetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\ResetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\UpdateGeneralSettingsAction;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Http\Request\UpdateGeneralSettingsRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings\GeneralSettingsResource;

/** @internal */
final class GeneralSettingsApiController extends Controller
{
    public function show(SettingsGate $gate, GetGeneralSettingsAction $get): JsonResource
    {
        $gate->authorize();

        return new GeneralSettingsResource($get());
    }

    public function update(
        SettingsGate $gate,
        UpdateGeneralSettingsRequest $request,
        UpdateGeneralSettingsAction $update,
        GetGeneralSettingsAction $get,
    ): JsonResource {
        $gate->authorize();

        $update($request->settings());

        return new GeneralSettingsResource($get());
    }

    public function reset(
        SettingsGate $gate,
        ResetGeneralSettingsAction $resetAction,
        GetGeneralSettingsAction $get,
    ): JsonResource {
        $gate->authorize();

        $resetAction();

        return new GeneralSettingsResource($get());
    }
}
