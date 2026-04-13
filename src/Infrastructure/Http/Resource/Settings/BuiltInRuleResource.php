<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yammi\JobsMonitor\Application\DTO\BuiltInRuleData;

/**
 * @internal
 *
 * @property-read BuiltInRuleData $resource
 */
final class BuiltInRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'trigger' => $this->resource->trigger->value,
            'trigger_value' => $this->resource->triggerValue,
            'window' => $this->resource->window,
            'threshold' => $this->resource->threshold,
            'cooldown_minutes' => $this->resource->cooldownMinutes,
            'min_attempt' => $this->resource->minAttempt,
            'channels' => $this->resource->channels,
            'code_default_enabled' => $this->resource->codeDefaultEnabled,
            'effectively_enabled' => $this->resource->effectivelyEnabled,
            'has_override' => $this->resource->hasOverride,
            'override_rule_id' => $this->resource->overrideRuleId,
        ];
    }
}
