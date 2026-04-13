<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yammi\JobsMonitor\Presentation\ViewModel\Settings\FeatureBlockViewModel;

/**
 * @internal
 *
 * @property-read FeatureBlockViewModel $resource
 */
final class FeatureBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'enabled' => $this->resource->enabled,
        ];
    }
}
