<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yammi\JobsMonitor\Application\DTO\AlertSettingsData;

/**
 * @internal
 *
 * @property-read AlertSettingsData $resource
 */
final class AlertSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->resource->enabled,
            'enabled_source' => $this->resource->enabledSource->value,
            'source_name' => $this->resource->sourceName,
            'source_name_source' => $this->resource->sourceNameSource->value,
            'monitor_url' => $this->resource->monitorUrl,
            'monitor_url_source' => $this->resource->monitorUrlSource->value,
            'recipients' => $this->resource->recipients,
            'recipients_source' => $this->resource->recipientsSource->value,
            'channels' => array_map(
                static fn ($c) => [
                    'name' => $c->name,
                    'label' => $c->label,
                    'icon' => $c->icon,
                    'purpose' => $c->purpose,
                    'configured' => $c->configured,
                    'env_var' => $c->envVar,
                ],
                $this->resource->channels,
            ),
        ];
    }
}
