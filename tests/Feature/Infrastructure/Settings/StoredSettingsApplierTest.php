<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Settings;

use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\StoredSettingsApplier;
use Yammi\JobsMonitor\Tests\TestCase;

final class StoredSettingsApplierTest extends TestCase
{
    public function test_stored_db_value_overrides_config(): void
    {
        config()->set('jobs-monitor.retention_days', 30);

        $this->app->make(GeneralSettingRepository::class)
            ->set('general', 'retention_days', '90', 'integer');

        $this->app->make(StoredSettingsApplier::class)->apply();

        self::assertSame(90, config('jobs-monitor.retention_days'));
    }

    public function test_config_untouched_when_no_db_value(): void
    {
        config()->set('jobs-monitor.retention_days', 30);

        $this->app->make(StoredSettingsApplier::class)->apply();

        self::assertSame(30, config('jobs-monitor.retention_days'));
    }

    public function test_casts_value_to_declared_type(): void
    {
        $this->app->make(GeneralSettingRepository::class)
            ->set('general', 'store_payload', '1', 'boolean');

        $this->app->make(StoredSettingsApplier::class)->apply();

        self::assertTrue(config('jobs-monitor.store_payload'));
    }
}
