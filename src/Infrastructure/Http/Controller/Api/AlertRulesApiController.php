<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\DeleteManagedAlertRuleAction;
use Yammi\JobsMonitor\Application\Action\ListAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\SaveManagedAlertRuleAction;
use Yammi\JobsMonitor\Application\Action\ToggleBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Http\Request\SaveAlertRuleRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Request\ToggleBuiltInRuleRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings\AlertRulesOverviewResource;
use Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings\ManagedAlertRuleResource;

/** @internal */
final class AlertRulesApiController extends Controller
{
    public function index(SettingsGate $gate, ListAlertRulesAction $list): JsonResource
    {
        $gate->authorize();

        return new AlertRulesOverviewResource($list());
    }

    public function show(
        SettingsGate $gate,
        int $id,
        ManagedAlertRuleRepository $repo,
    ): JsonResource|JsonResponse {
        $gate->authorize();

        $rule = $repo->findById($id);
        if ($rule === null) {
            return new JsonResponse(['message' => 'Alert rule not found.'], 404);
        }

        return new ManagedAlertRuleResource($rule);
    }

    public function store(
        SettingsGate $gate,
        SaveAlertRuleRequest $request,
        SaveManagedAlertRuleAction $save,
    ): JsonResource {
        $gate->authorize();

        return new ManagedAlertRuleResource($save($request->buildEntity()));
    }

    public function update(
        SettingsGate $gate,
        int $id,
        SaveAlertRuleRequest $request,
        SaveManagedAlertRuleAction $save,
        ManagedAlertRuleRepository $repo,
    ): JsonResource|JsonResponse {
        $gate->authorize();

        if ($repo->findById($id) === null) {
            return new JsonResponse(['message' => 'Alert rule not found.'], 404);
        }

        return new ManagedAlertRuleResource($save($request->buildEntity($id)));
    }

    public function destroy(
        SettingsGate $gate,
        int $id,
        DeleteManagedAlertRuleAction $delete,
    ): JsonResponse {
        $gate->authorize();
        $deleted = $delete($id);

        return new JsonResponse(null, $deleted ? 204 : 404);
    }

    public function toggleBuiltIn(
        SettingsGate $gate,
        string $key,
        ToggleBuiltInRuleRequest $request,
        ToggleBuiltInRuleAction $toggle,
        BuiltInRulesProvider $provider,
        ListAlertRulesAction $list,
    ): JsonResource|JsonResponse {
        $gate->authorize();

        if (! array_key_exists($key, $provider->catalog())) {
            return new JsonResponse(['message' => 'Built-in rule not found.'], 404);
        }

        $toggle($key, $request->enabled());

        return new AlertRulesOverviewResource($list());
    }
}
