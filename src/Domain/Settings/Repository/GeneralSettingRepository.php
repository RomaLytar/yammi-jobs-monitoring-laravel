<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\Repository;

/**
 * Persistence contract for EAV-style general settings.
 *
 * Values are stored as raw strings; casting is the caller's
 * responsibility (via SettingType).
 */
interface GeneralSettingRepository
{
    /**
     * @return array<string, array<string, string>> group => [key => raw_value]
     */
    public function all(): array;

    public function get(string $group, string $key): ?string;

    public function set(string $group, string $key, string $value, string $type): void;

    public function remove(string $group, string $key): void;
}
