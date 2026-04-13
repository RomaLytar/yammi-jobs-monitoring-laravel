<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\AddAlertRecipientsAction;
use Yammi\JobsMonitor\Application\Action\GetAlertSettingsAction;
use Yammi\JobsMonitor\Application\Action\RemoveAlertRecipientAction;
use Yammi\JobsMonitor\Application\Action\ToggleAlertsAction;
use Yammi\JobsMonitor\Application\Action\UpdateAlertScalarSettingsAction;
use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidEmailRecipient;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Http\Request\AddAlertRecipientsRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Request\ToggleAlertsRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Request\UpdateAlertScalarsRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings\AlertSettingsResource;

/** @internal */
final class AlertSettingsApiController extends Controller
{
    public function show(SettingsGate $gate, GetAlertSettingsAction $get): JsonResource
    {
        $gate->authorize();

        return new AlertSettingsResource($get());
    }

    public function toggle(
        SettingsGate $gate,
        ToggleAlertsRequest $request,
        ToggleAlertsAction $toggle,
        GetAlertSettingsAction $get,
    ): JsonResource {
        $gate->authorize();
        $toggle($request->enabled());

        return new AlertSettingsResource($get());
    }

    public function update(
        SettingsGate $gate,
        UpdateAlertScalarsRequest $request,
        UpdateAlertScalarSettingsAction $update,
        GetAlertSettingsAction $get,
    ): JsonResource {
        $gate->authorize();

        $url = $request->monitorUrl();
        $update($request->sourceName(), $url === null ? null : new MonitorUrl($url));

        return new AlertSettingsResource($get());
    }

    public function addRecipients(
        SettingsGate $gate,
        AddAlertRecipientsRequest $request,
        AddAlertRecipientsAction $add,
        GetAlertSettingsAction $get,
    ): JsonResource|JsonResponse {
        $gate->authorize();

        try {
            $add($request->emails());
        } catch (InvalidEmailRecipient $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'errors' => ['emails' => [$e->getMessage()]],
            ], 422);
        }

        return new AlertSettingsResource($get());
    }

    public function removeRecipient(
        SettingsGate $gate,
        string $email,
        RemoveAlertRecipientAction $remove,
        GetAlertSettingsAction $get,
    ): JsonResource {
        $gate->authorize();
        $remove(urldecode($email));

        return new AlertSettingsResource($get());
    }
}
