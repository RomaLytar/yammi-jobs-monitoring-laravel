<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

enum SettingType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Float = 'float';
    case String = 'string';

    public function cast(string $raw): bool|int|float|string
    {
        return match ($this) {
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Integer => (int) $raw,
            self::Float => (float) $raw,
            self::String => $raw,
        };
    }

    public function serialize(bool|int|float|string $value): string
    {
        return match ($this) {
            self::Boolean => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
