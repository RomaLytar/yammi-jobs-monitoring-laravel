<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yammi\JobsMonitor\Presentation\ViewModel\Settings\SettingsIndexViewModel;

/**
 * @internal
 *
 * @property-read SettingsIndexViewModel $resource
 */
final class SettingsIndexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'features' => FeatureBlockResource::collection($this->resource->features),
        ];
    }
}
