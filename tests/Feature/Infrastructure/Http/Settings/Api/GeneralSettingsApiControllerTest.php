<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings\Api;

use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Tests\TestCase;

final class GeneralSettingsApiControllerTest extends TestCase
{
    /**
     * @define-env enableApi
     */
    public function test_show_returns_all_groups(): void
    {
        $response = $this->getJson('/api/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'groups' => [
                    [
                        'key',
                        'label',
                        'description',
                        'icon',
                        'settings' => [
                            [
                                'group',
                                'key',
                                'label',
                                'description',
                                'type',
                                'value',
                                'source',
                                'default',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $response->assertJsonPath('data.groups.0.key', 'general');
    }

    /**
     * @define-env enableApi
     */
    public function test_show_returns_config_source_when_config_is_loaded(): void
    {
        $response = $this->getJson('/api/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertJsonPath('data.groups.0.settings.0.source', 'config');
    }

    /**
     * @define-env enableApi
     */
    public function test_update_persists_and_returns_db_source(): void
    {
        $response = $this->putJson('/api/jobs-monitor/settings/general', [
            'settings' => [
                'general' => [
                    'store_payload' => '0',
                    'retention_days' => '7',
                    'max_tries' => '5',
                ],
                'bulk' => [
                    'max_ids_per_request' => '100',
                    'candidate_limit' => '10000',
                ],
                'scheduler' => [
                    'enabled' => '1',
                    'watchdog_enabled' => '1',
                    'watchdog_tolerance_minutes' => '30',
                ],
                'duration_anomaly' => [
                    'enabled' => '1',
                    'min_samples' => '30',
                    'short_factor' => '0.1',
                    'long_factor' => '3.0',
                ],
                'outcome' => [
                    'enabled' => '1',
                ],
                'workers' => [
                    'enabled' => '1',
                    'heartbeat_interval_seconds' => '30',
                    'silent_after_seconds' => '120',
                    'retention_days' => '7',
                    'schedule_cron' => '* * * * *',
                ],
                'alerts_schedule' => [
                    'schedule_enabled' => '1',
                    'schedule_cron' => '* * * * *',
                    'schedule_queue' => '',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.groups.0.settings.1.source', 'db');
        $response->assertJsonPath('data.groups.0.settings.1.value', 7);
    }

    /**
     * @define-env enableApi
     */
    public function test_update_validates_out_of_range(): void
    {
        $response = $this->putJson('/api/jobs-monitor/settings/general', [
            'settings' => [
                'general' => [
                    'store_payload' => '0',
                    'retention_days' => '999',
                    'max_tries' => '3',
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function test_show_returns_404_when_api_disabled(): void
    {
        $response = $this->getJson('/api/jobs-monitor/settings/general');

        $response->assertNotFound();
    }

    /**
     * @param  Application  $app
     */
    protected function enableApi($app): void
    {
        $app['config']->set('jobs-monitor.api.enabled', true);
    }
}
