<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\GetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Contract\ConfigReader;
use Yammi\JobsMonitor\Application\DTO\SettingGroupData;
use Yammi\JobsMonitor\Application\DTO\ValueSource;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Tests\Support\ArrayConfigReader;
use Yammi\JobsMonitor\Tests\Support\InMemoryGeneralSettingRepository;

final class GetGeneralSettingsActionTest extends TestCase
{
    public function test_returns_defaults_when_db_and_config_empty(): void
    {
        $groups = $this->buildAction()();

        self::assertNotEmpty($groups);
        self::assertContainsOnlyInstancesOf(SettingGroupData::class, $groups);

        $general = $this->findGroup($groups, 'general');
        self::assertNotNull($general);

        $retention = $this->findSetting($general, 'retention_days');
        self::assertNotNull($retention);
        self::assertSame(30, $retention->value);
        self::assertSame(ValueSource::Default, $retention->source);
    }

    public function test_config_value_beats_default(): void
    {
        $config = new ArrayConfigReader([
            'jobs-monitor' => ['retention_days' => 14],
        ]);

        $groups = $this->buildAction(config: $config)();

        $retention = $this->findSetting($this->findGroup($groups, 'general'), 'retention_days');
        self::assertSame(14, $retention->value);
        self::assertSame(ValueSource::Config, $retention->source);
    }

    public function test_db_value_beats_config(): void
    {
        $repo = new InMemoryGeneralSettingRepository;
        $repo->set('general', 'retention_days', '7', 'integer');

        $config = new ArrayConfigReader([
            'jobs-monitor' => ['retention_days' => 14],
        ]);

        $groups = $this->buildAction(repo: $repo, config: $config)();

        $retention = $this->findSetting($this->findGroup($groups, 'general'), 'retention_days');
        self::assertSame(7, $retention->value);
        self::assertSame(ValueSource::Db, $retention->source);
    }

    public function test_boolean_setting_resolves_from_db(): void
    {
        $repo = new InMemoryGeneralSettingRepository;
        $repo->set('general', 'store_payload', '1', 'boolean');

        $groups = $this->buildAction(repo: $repo)();

        $storePayload = $this->findSetting($this->findGroup($groups, 'general'), 'store_payload');
        self::assertTrue($storePayload->value);
        self::assertSame(ValueSource::Db, $storePayload->source);
    }

    public function test_float_setting_resolves_from_db(): void
    {
        $repo = new InMemoryGeneralSettingRepository;
        $repo->set('duration_anomaly', 'short_factor', '0.05', 'float');

        $groups = $this->buildAction(repo: $repo)();

        $factor = $this->findSetting($this->findGroup($groups, 'duration_anomaly'), 'short_factor');
        self::assertSame(0.05, $factor->value);
        self::assertSame(ValueSource::Db, $factor->source);
    }

    public function test_string_setting_resolves_from_db(): void
    {
        $repo = new InMemoryGeneralSettingRepository;
        $repo->set('workers', 'schedule_cron', '*/5 * * * *', 'string');

        $groups = $this->buildAction(repo: $repo)();

        $cron = $this->findSetting($this->findGroup($groups, 'workers'), 'schedule_cron');
        self::assertSame('*/5 * * * *', $cron->value);
        self::assertSame(ValueSource::Db, $cron->source);
    }

    public function test_all_groups_have_settings_with_descriptions(): void
    {
        $groups = $this->buildAction()();

        foreach ($groups as $group) {
            self::assertNotEmpty($group->label);
            self::assertNotEmpty($group->description);
            self::assertNotEmpty($group->icon);
            foreach ($group->settings as $setting) {
                self::assertNotEmpty($setting->description, "Setting {$setting->group}.{$setting->key} has no description");
            }
        }
    }

    /**
     * @param  list<SettingGroupData>  $groups
     */
    private function findGroup(array $groups, string $key): ?SettingGroupData
    {
        foreach ($groups as $group) {
            if ($group->key === $key) {
                return $group;
            }
        }

        return null;
    }

    private function findSetting(
        ?SettingGroupData $group,
        string $key,
    ): ?\Yammi\JobsMonitor\Application\DTO\ResolvedSettingData {
        if ($group === null) {
            return null;
        }

        foreach ($group->settings as $setting) {
            if ($setting->key === $key) {
                return $setting;
            }
        }

        return null;
    }

    private function buildAction(
        ?InMemoryGeneralSettingRepository $repo = null,
        ?ConfigReader $config = null,
    ): GetGeneralSettingsAction {
        return new GetGeneralSettingsAction(
            repo: $repo ?? new InMemoryGeneralSettingRepository,
            registry: new SettingRegistry,
            config: $config ?? new ArrayConfigReader,
        );
    }
}
