<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;

/**
 * @internal
 *
 * @property-read ManagedAlertRule $resource
 */
final class ManagedAlertRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rule = $this->resource->rule();

        return [
            'id' => $this->resource->id(),
            'key' => $this->resource->key(),
            'trigger' => $rule->trigger->value,
            'trigger_value' => $rule->triggerValue,
            'window' => $rule->window,
            'threshold' => $rule->threshold,
            'cooldown_minutes' => $rule->cooldownMinutes,
            'min_attempt' => $rule->minAttempt,
            'channels' => $rule->channels,
            'enabled' => $this->resource->isEnabled(),
            'overrides_built_in' => $this->resource->overridesBuiltIn(),
            'position' => $this->resource->position(),
        ];
    }
}
