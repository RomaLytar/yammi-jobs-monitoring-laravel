<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

final class SettingGroupData
{
    /**
     * @param  list<ResolvedSettingData>  $settings
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $icon,
        public readonly array $settings,
    ) {}
}
