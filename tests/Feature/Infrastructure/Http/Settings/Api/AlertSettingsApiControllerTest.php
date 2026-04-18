<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings\Api;

use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\TestCase;

final class AlertSettingsApiControllerTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    public function test_show_returns_default_state(): void
    {
        $response = $this->getJson('/api/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'enabled', 'enabled_source',
                'source_name', 'source_name_source',
                'monitor_url', 'monitor_url_source',
                'recipients', 'recipients_source',
<<<<<<< HEAD
                'channels' => [
                    '*' => ['name', 'label', 'icon', 'purpose', 'configured', 'env_var'],
                ],
=======
>>>>>>> origin/main
            ],
        ]);
        // Config ships alerts.enabled defaulting to false — source is 'config'
        $response->assertJsonPath('data.enabled', false);
        $response->assertJsonPath('data.enabled_source', 'config');
        // Source name / monitor URL auto-derive from app.name and app.url — 'auto'
        $response->assertJsonPath('data.source_name_source', 'auto');
        $response->assertJsonPath('data.monitor_url_source', 'auto');
        // Recipients have no auto-derivation, so they remain 'default'
        $response->assertJsonPath('data.recipients_source', 'default');
    }

<<<<<<< HEAD
    public function test_channels_list_contains_five_channels_with_configured_flag(): void
    {
        config([
            'jobs-monitor.alerts.channels.pagerduty.routing_key' => 'rk-test-xyz',
            'jobs-monitor.alerts.channels.webhook.url' => 'https://webhook.test/hook',
        ]);

        $response = $this->getJson('/api/jobs-monitor/settings/alerts');

        $response->assertOk();
        $channels = $response->json('data.channels');
        self::assertIsArray($channels);
        self::assertCount(5, $channels);

        $byName = [];
        foreach ($channels as $entry) {
            $byName[$entry['name']] = $entry;
        }

        self::assertTrue($byName['pagerduty']['configured']);
        self::assertTrue($byName['webhook']['configured']);
        self::assertFalse($byName['opsgenie']['configured']);
        self::assertSame('JOBS_MONITOR_OPSGENIE_API_KEY', $byName['opsgenie']['env_var']);
    }

=======
>>>>>>> origin/main
    public function test_show_reflects_db_overrides(): void
    {
        $this->repo()->save(new AlertSettings(
            true, 'Production', null,
            new EmailRecipientList(['ops@example.com']),
        ));

        $response = $this->getJson('/api/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.enabled_source', 'db');
        $response->assertJsonPath('data.source_name', 'Production');
        $response->assertJsonPath('data.source_name_source', 'db');
        $response->assertJsonPath('data.recipients', ['ops@example.com']);
        $response->assertJsonPath('data.recipients_source', 'db');
    }

    public function test_toggle_persists_and_returns_state(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/toggle', ['enabled' => true]);

        $response->assertOk();
        $response->assertJsonPath('data.enabled', true);
        self::assertTrue($this->repo()->get()->isEnabled());
    }

    public function test_update_persists_scalars(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->putJson('/api/jobs-monitor/settings/alerts', [
            'source_name' => 'Production',
            'monitor_url' => 'https://monitor.example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.source_name', 'Production');
        $response->assertJsonPath('data.monitor_url', 'https://monitor.example.com');
    }

    public function test_update_validates_monitor_url(): void
    {
        $response = $this->putJson('/api/jobs-monitor/settings/alerts', ['monitor_url' => 'not-a-url']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['monitor_url']);
    }

    public function test_add_recipient_accepts_single_email_string(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/recipients', ['email' => 'ops@example.com']);

        $response->assertOk();
        $response->assertJsonPath('data.recipients', ['ops@example.com']);
    }

    public function test_add_recipient_accepts_multiple_emails_in_array(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/recipients', [
            'emails' => ['ops@example.com', 'sre@example.com'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.recipients', ['ops@example.com', 'sre@example.com']);
    }

    public function test_add_recipient_accepts_comma_separated_string(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/recipients', [
            'email' => 'ops@example.com, sre@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.recipients', ['ops@example.com', 'sre@example.com']);
    }

    public function test_add_recipient_returns_422_on_duplicate(): void
    {
        $this->repo()->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com']),
        ));

        $response = $this->postJson('/api/jobs-monitor/settings/alerts/recipients', ['emails' => ['OPS@example.com']]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['emails']);
    }

    public function test_remove_recipient_drops_email_and_returns_list(): void
    {
        $this->repo()->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com', 'sre@example.com']),
        ));

        $response = $this->deleteJson('/api/jobs-monitor/settings/alerts/recipients/'.urlencode('ops@example.com'));

        $response->assertOk();
        $response->assertJsonPath('data.recipients', ['sre@example.com']);
    }

    private function repo(): AlertSettingsRepository
    {
        return $this->app->make(AlertSettingsRepository::class);
    }
}
