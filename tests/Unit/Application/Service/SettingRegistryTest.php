<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\DTO\SettingDefinitionData;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;

final class SettingRegistryTest extends TestCase
{
    private SettingRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SettingRegistry();
    }

    public function test_groups_returns_non_empty_array(): void
    {
        self::assertNotEmpty($this->registry->groups());
    }

    public function test_every_group_has_required_keys(): void
    {
        foreach ($this->registry->groups() as $key => $group) {
            self::assertIsString($key, "Group key must be a string");
            self::assertArrayHasKey('label', $group);
            self::assertArrayHasKey('description', $group);
            self::assertArrayHasKey('icon', $group);
            self::assertArrayHasKey('settings', $group);
            self::assertNotEmpty($group['settings'], "Group '{$key}' must have at least one setting");
        }
    }

    public function test_every_setting_has_matching_group_key(): void
    {
        foreach ($this->registry->groups() as $groupKey => $group) {
            foreach ($group['settings'] as $setting) {
                self::assertInstanceOf(SettingDefinitionData::class, $setting);
                self::assertSame($groupKey, $setting->group);
            }
        }
    }

    public function test_every_setting_has_non_empty_label_and_description(): void
    {
        foreach ($this->registry->groups() as $group) {
            foreach ($group['settings'] as $setting) {
                self::assertNotEmpty($setting->label, "Setting {$setting->group}.{$setting->key} must have a label");
                self::assertNotEmpty($setting->description, "Setting {$setting->group}.{$setting->key} must have a description");
            }
        }
    }

    public function test_every_setting_has_config_path(): void
    {
        foreach ($this->registry->groups() as $group) {
            foreach ($group['settings'] as $setting) {
                self::assertStringStartsWith(
                    'jobs-monitor.',
                    $setting->configPath,
                    "Setting {$setting->group}.{$setting->key} config path must start with jobs-monitor.",
                );
            }
        }
    }

    public function test_no_duplicate_group_key_pairs(): void
    {
        $seen = [];
        foreach ($this->registry->groups() as $group) {
            foreach ($group['settings'] as $setting) {
                $composite = "{$setting->group}.{$setting->key}";
                self::assertArrayNotHasKey($composite, $seen, "Duplicate setting: {$composite}");
                $seen[$composite] = true;
            }
        }
    }

    public function test_find_returns_known_setting(): void
    {
        $result = $this->registry->find('general', 'retention_days');

        self::assertNotNull($result);
        self::assertSame('retention_days', $result->key);
        self::assertSame('general', $result->group);
    }

    public function test_find_returns_null_for_unknown_setting(): void
    {
        self::assertNull($this->registry->find('general', 'nonexistent'));
    }

    public function test_find_returns_null_for_unknown_group(): void
    {
        self::assertNull($this->registry->find('nonexistent', 'enabled'));
    }

    public function test_expected_groups_exist(): void
    {
        $groups = $this->registry->groups();

        self::assertArrayHasKey('general', $groups);
        self::assertArrayHasKey('bulk', $groups);
        self::assertArrayHasKey('scheduler', $groups);
        self::assertArrayHasKey('duration_anomaly', $groups);
        self::assertArrayHasKey('outcome', $groups);
        self::assertArrayHasKey('workers', $groups);
        self::assertArrayHasKey('alerts_schedule', $groups);
    }
}
