<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

final class ResolvedSettingData
{
    /**
     * @param  array<string, string>|null  $options  value => human label for select inputs
     */
    public function __construct(
        public readonly string $group,
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly SettingType $type,
        public readonly bool|int|float|string $value,
        public readonly ValueSource $source,
        public readonly bool|int|float|string $default,
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
        public readonly ?string $suffix = null,
        public readonly ?array $options = null,
        public readonly ?string $pattern = null,
    ) {}
}
