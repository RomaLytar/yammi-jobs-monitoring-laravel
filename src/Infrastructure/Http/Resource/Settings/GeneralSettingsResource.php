<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Resource\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yammi\JobsMonitor\Application\DTO\SettingGroupData;

/**
 * @internal
 *
 * @property-read list<SettingGroupData> $resource
 */
final class GeneralSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'groups' => array_map(static fn (SettingGroupData $group): array => [
                'key' => $group->key,
                'label' => $group->label,
                'description' => $group->description,
                'icon' => $group->icon,
                'settings' => array_map(static fn ($s): array => [
                    'group' => $s->group,
                    'key' => $s->key,
                    'label' => $s->label,
                    'description' => $s->description,
                    'type' => $s->type->value,
                    'value' => $s->value,
                    'source' => $s->source->value,
                    'default' => $s->default,
                    'min' => $s->min,
                    'max' => $s->max,
                    'suffix' => $s->suffix,
                    'options' => $s->options,
                    'pattern' => $s->pattern,
                ], $group->settings),
            ], $this->resource),
        ];
    }
}
