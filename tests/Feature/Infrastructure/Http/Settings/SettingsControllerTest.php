<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings;

use Illuminate\Support\Facades\Gate;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\TestCase;

final class SettingsControllerTest extends TestCase
{
    public function test_index_renders_with_alerts_feature_block(): void
    {
        $response = $this->get('/jobs-monitor/settings');

        $response->assertOk();
        $response->assertSee('Settings');
        $response->assertSee('Alerts');
    }

    public function test_index_shows_disabled_badge_when_alerts_off(): void
    {
        $response = $this->get('/jobs-monitor/settings');

        $response->assertOk();
        $response->assertSee('Disabled');
    }

    public function test_index_shows_enabled_badge_when_alerts_enabled_in_db(): void
    {
        $repo = $this->app->make(AlertSettingsRepository::class);
        $repo->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->get('/jobs-monitor/settings');

        $response->assertOk();
        $response->assertSee('Enabled');
    }

    public function test_index_returns_403_when_gate_denies(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jobs-monitor.settings');
        Gate::define('jobs-monitor.settings', static fn () => false);

        $response = $this->get('/jobs-monitor/settings');

        $response->assertForbidden();
    }

    public function test_dashboard_navigation_includes_settings_link(): void
    {
        $response = $this->get('/jobs-monitor');

        $response->assertOk();
        $response->assertSee('href="'.url('/jobs-monitor/settings').'"', false);
    }
}
