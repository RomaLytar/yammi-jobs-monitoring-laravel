<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\UpdateGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Tests\Support\InMemoryGeneralSettingRepository;

final class UpdateGeneralSettingsActionTest extends TestCase
{
    public function test_persists_boolean_setting(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action(['general' => ['store_payload' => true]]);

        self::assertSame('1', $repo->get('general', 'store_payload'));
    }

    public function test_persists_integer_setting(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action(['general' => ['retention_days' => 14]]);

        self::assertSame('14', $repo->get('general', 'retention_days'));
    }

    public function test_persists_float_setting(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action(['duration_anomaly' => ['short_factor' => 0.05]]);

        self::assertSame('0.05', $repo->get('duration_anomaly', 'short_factor'));
    }

    public function test_persists_string_setting(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action(['workers' => ['schedule_cron' => '*/5 * * * *']]);

        self::assertSame('*/5 * * * *', $repo->get('workers', 'schedule_cron'));
    }

    public function test_null_value_removes_setting(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $repo->set('general', 'retention_days', '14', 'integer');
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action(['general' => ['retention_days' => null]]);

        self::assertNull($repo->get('general', 'retention_days'));
    }

    public function test_ignores_unknown_group(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action(['nonexistent' => ['foo' => 'bar']]);

        self::assertSame([], $repo->all());
    }

    public function test_ignores_unknown_key_within_known_group(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action(['general' => ['nonexistent' => 'bar']]);

        self::assertSame([], $repo->all());
    }

    public function test_persists_multiple_groups_at_once(): void
    {
        $repo = new InMemoryGeneralSettingRepository();
        $action = new UpdateGeneralSettingsAction($repo, new SettingRegistry());

        $action([
            'general' => ['retention_days' => 7, 'max_tries' => 5],
            'workers' => ['enabled' => false],
        ]);

        self::assertSame('7', $repo->get('general', 'retention_days'));
        self::assertSame('5', $repo->get('general', 'max_tries'));
        self::assertSame('0', $repo->get('workers', 'enabled'));
    }
}
