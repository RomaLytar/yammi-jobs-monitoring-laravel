<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Settings;

use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class EloquentGeneralSettingRepositoryTest extends TestCase
{
    private GeneralSettingRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->app->make(GeneralSettingRepository::class);
    }

    public function test_all_returns_empty_when_no_settings_exist(): void
    {
        self::assertSame([], $this->repo->all());
    }

    public function test_set_and_get_returns_value(): void
    {
        $this->repo->set('general', 'retention_days', '14', 'integer');

        self::assertSame('14', $this->repo->get('general', 'retention_days'));
    }

    public function test_set_upserts_on_duplicate_group_key(): void
    {
        $this->repo->set('general', 'retention_days', '14', 'integer');
        $this->repo->set('general', 'retention_days', '7', 'integer');

        self::assertSame('7', $this->repo->get('general', 'retention_days'));
    }

    public function test_get_returns_null_for_nonexistent_setting(): void
    {
        self::assertNull($this->repo->get('general', 'nonexistent'));
    }

    public function test_remove_deletes_setting(): void
    {
        $this->repo->set('general', 'retention_days', '14', 'integer');
        $this->repo->remove('general', 'retention_days');

        self::assertNull($this->repo->get('general', 'retention_days'));
    }

    public function test_remove_is_idempotent(): void
    {
        $this->repo->remove('general', 'nonexistent');

        self::assertNull($this->repo->get('general', 'nonexistent'));
    }

    public function test_all_returns_grouped_values(): void
    {
        $this->repo->set('general', 'retention_days', '14', 'integer');
        $this->repo->set('general', 'max_tries', '5', 'integer');
        $this->repo->set('workers', 'enabled', '1', 'boolean');

        $all = $this->repo->all();

        self::assertSame('14', $all['general']['retention_days']);
        self::assertSame('5', $all['general']['max_tries']);
        self::assertSame('1', $all['workers']['enabled']);
    }

    public function test_set_boolean_value(): void
    {
        $this->repo->set('general', 'store_payload', '1', 'boolean');

        self::assertSame('1', $this->repo->get('general', 'store_payload'));
    }

    public function test_set_float_value(): void
    {
        $this->repo->set('duration_anomaly', 'short_factor', '0.05', 'float');

        self::assertSame('0.05', $this->repo->get('duration_anomaly', 'short_factor'));
    }

    public function test_set_string_value(): void
    {
        $this->repo->set('workers', 'schedule_cron', '*/5 * * * *', 'string');

        self::assertSame('*/5 * * * *', $this->repo->get('workers', 'schedule_cron'));
    }
}
