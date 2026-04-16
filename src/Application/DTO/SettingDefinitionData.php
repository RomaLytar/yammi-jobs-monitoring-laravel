<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

final class SettingDefinitionData
{
    /**
     * @param array<string, string>|null $options value => human label for select inputs
     */
    public function __construct(
        public readonly string $group,
        public readonly string $key,
        public readonly string $configPath,
        public readonly SettingType $type,
        public readonly bool|int|float|string $default,
        public readonly string $label,
        public readonly string $description,
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
        public readonly ?string $suffix = null,
        public readonly ?array $options = null,
        public readonly ?string $pattern = null,
    ) {}
}
