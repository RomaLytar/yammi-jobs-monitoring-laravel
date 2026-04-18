<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings;

use Illuminate\Support\Facades\Gate;
use Yammi\JobsMonitor\Tests\TestCase;

final class GeneralSettingsControllerTest extends TestCase
{
    public function test_index_renders_all_setting_groups(): void
    {
        $response = $this->get('/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertSee('General Settings');
        $response->assertSee('General');
        $response->assertSee('Bulk Operations');
        $response->assertSee('Scheduler Monitoring');
        $response->assertSee('Duration Anomaly Detection');
        $response->assertSee('Outcome Reports');
        $response->assertSee('Worker Heartbeat');
        $response->assertSee('Alerts Schedule');
    }

    public function test_index_renders_setting_descriptions(): void
    {
        $response = $this->get('/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertSee('Save the raw job payload alongside each record');
        $response->assertSee('Number of days to keep job records');
    }

    public function test_index_shows_source_badges(): void
    {
        $response = $this->get('/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertSee('from config');
    }

    public function test_index_shows_back_link_to_settings(): void
    {
        $response = $this->get('/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertSee('Back to settings');
    }

    public function test_update_persists_settings_and_redirects(): void
    {
        $response = $this->post('/jobs-monitor/settings/general', [
            'settings' => [
                'general' => [
                    'store_payload' => '1',
                    'retention_days' => '14',
                    'max_tries' => '5',
                ],
                'bulk' => [
                    'max_ids_per_request' => '50',
                    'candidate_limit' => '5000',
                ],
                'scheduler' => [
                    'enabled' => '1',
                    'watchdog_enabled' => '1',
                    'watchdog_tolerance_minutes' => '60',
                ],
                'duration_anomaly' => [
                    'enabled' => '1',
                    'min_samples' => '50',
                    'short_factor' => '0.2',
                    'long_factor' => '5.0',
                ],
                'outcome' => [
                    'enabled' => '1',
                ],
                'workers' => [
                    'enabled' => '1',
                    'heartbeat_interval_seconds' => '60',
                    'silent_after_seconds' => '180',
                    'retention_days' => '14',
                    'schedule_cron' => '*/5 * * * *',
                ],
                'alerts_schedule' => [
                    'schedule_enabled' => '1',
                    'schedule_cron' => '*/2 * * * *',
                    'schedule_queue' => 'monitoring',
                ],
            ],
        ]);

        $response->assertRedirect('/jobs-monitor/settings/general');
        $response->assertSessionHas('jobs_monitor_status', 'Settings saved.');
    }

    public function test_update_values_are_reflected_on_reload(): void
    {
        $this->post('/jobs-monitor/settings/general', [
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

        $response = $this->get('/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertSee('from DB');
    }

    public function test_update_validates_integer_range(): void
    {
        $response = $this->post('/jobs-monitor/settings/general', [
            'settings' => [
                'general' => [
                    'store_payload' => '0',
                    'retention_days' => '999',
                    'max_tries' => '3',
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

        $response->assertSessionHasErrors('settings.general.retention_days');
    }

    public function test_index_returns_403_when_gate_denies(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jobs-monitor.settings');
        Gate::define('jobs-monitor.settings', static fn () => false);

        $response = $this->get('/jobs-monitor/settings/general');

        $response->assertForbidden();
    }

    public function test_update_accepts_custom_cron_value(): void
    {
        $response = $this->post('/jobs-monitor/settings/general', [
            'settings' => [
                'general' => ['store_payload' => '1', 'retention_days' => '30', 'max_tries' => '3'],
                'bulk' => ['max_ids_per_request' => '100', 'candidate_limit' => '10000'],
                'scheduler' => ['enabled' => '1', 'watchdog_enabled' => '1', 'watchdog_tolerance_minutes' => '30'],
                'duration_anomaly' => ['enabled' => '1', 'min_samples' => '30', 'short_factor' => '0.1', 'long_factor' => '3.0'],
                'outcome' => ['enabled' => '1'],
                'workers' => ['enabled' => '1', 'heartbeat_interval_seconds' => '30', 'silent_after_seconds' => '120', 'retention_days' => '7', 'schedule_cron' => '*/3 * * * *'],
                'alerts_schedule' => ['schedule_enabled' => '1', 'schedule_cron' => '0 */2 * * *', 'schedule_queue' => ''],
            ],
        ]);

        $response->assertRedirect('/jobs-monitor/settings/general');
        $response->assertSessionHas('jobs_monitor_status', 'Settings saved.');
    }

    public function test_update_rejects_invalid_cron_value(): void
    {
        $response = $this->post('/jobs-monitor/settings/general', [
            'settings' => [
                'general' => ['store_payload' => '1', 'retention_days' => '30', 'max_tries' => '3'],
                'bulk' => ['max_ids_per_request' => '100', 'candidate_limit' => '10000'],
                'scheduler' => ['enabled' => '1', 'watchdog_enabled' => '1', 'watchdog_tolerance_minutes' => '30'],
                'duration_anomaly' => ['enabled' => '1', 'min_samples' => '30', 'short_factor' => '0.1', 'long_factor' => '3.0'],
                'outcome' => ['enabled' => '1'],
                'workers' => ['enabled' => '1', 'heartbeat_interval_seconds' => '30', 'silent_after_seconds' => '120', 'retention_days' => '7', 'schedule_cron' => 'garbage input'],
                'alerts_schedule' => ['schedule_enabled' => '1', 'schedule_cron' => '* * * * *', 'schedule_queue' => ''],
            ],
        ]);

        $response->assertSessionHasErrors('settings.workers.schedule_cron');
    }

    public function test_update_rejects_invalid_queue_name(): void
    {
        $response = $this->post('/jobs-monitor/settings/general', [
            'settings' => [
                'general' => ['store_payload' => '1', 'retention_days' => '30', 'max_tries' => '3'],
                'bulk' => ['max_ids_per_request' => '100', 'candidate_limit' => '10000'],
                'scheduler' => ['enabled' => '1', 'watchdog_enabled' => '1', 'watchdog_tolerance_minutes' => '30'],
                'duration_anomaly' => ['enabled' => '1', 'min_samples' => '30', 'short_factor' => '0.1', 'long_factor' => '3.0'],
                'outcome' => ['enabled' => '1'],
                'workers' => ['enabled' => '1', 'heartbeat_interval_seconds' => '30', 'silent_after_seconds' => '120', 'retention_days' => '7', 'schedule_cron' => '* * * * *'],
                'alerts_schedule' => ['schedule_enabled' => '1', 'schedule_cron' => '* * * * *', 'schedule_queue' => 'invalid queue!@#'],
            ],
        ]);

        $response->assertSessionHasErrors('settings.alerts_schedule.schedule_queue');
    }

    public function test_reset_clears_all_db_overrides(): void
    {
        $this->post('/jobs-monitor/settings/general', [
            'settings' => [
                'general' => ['store_payload' => '0', 'retention_days' => '7', 'max_tries' => '5'],
                'bulk' => ['max_ids_per_request' => '100', 'candidate_limit' => '10000'],
                'scheduler' => ['enabled' => '1', 'watchdog_enabled' => '1', 'watchdog_tolerance_minutes' => '30'],
                'duration_anomaly' => ['enabled' => '1', 'min_samples' => '30', 'short_factor' => '0.1', 'long_factor' => '3.0'],
                'outcome' => ['enabled' => '1'],
                'workers' => ['enabled' => '1', 'heartbeat_interval_seconds' => '30', 'silent_after_seconds' => '120', 'retention_days' => '7', 'schedule_cron' => '* * * * *'],
                'alerts_schedule' => ['schedule_enabled' => '1', 'schedule_cron' => '* * * * *', 'schedule_queue' => ''],
            ],
        ]);

        $response = $this->post('/jobs-monitor/settings/general/reset');

        $response->assertRedirect('/jobs-monitor/settings/general');
        $response->assertSessionHas('jobs_monitor_status', 'All settings reset to package defaults.');

        $page = $this->get('/jobs-monitor/settings/general');
        $page->assertDontSee('from DB');
    }

    public function test_index_shows_reset_button(): void
    {
        $response = $this->get('/jobs-monitor/settings/general');

        $response->assertOk();
        $response->assertSee('Reset to defaults');
    }

    public function test_settings_index_has_general_settings_card(): void
    {
        $response = $this->get('/jobs-monitor/settings');

        $response->assertOk();
        $response->assertSee('General Settings');
        $response->assertSee('Configure');
    }
}
