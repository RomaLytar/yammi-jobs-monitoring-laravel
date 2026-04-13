<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yammi\JobsMonitor\Application\DTO\AlertRulesOverviewData;

/**
 * @internal
 *
 * @property-read AlertRulesOverviewData $resource
 */
final class AlertRulesOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'built_in_rules' => BuiltInRuleResource::collection($this->resource->builtInRules),
            'user_rules' => ManagedAlertRuleResource::collection($this->resource->userRules),
        ];
    }
}
