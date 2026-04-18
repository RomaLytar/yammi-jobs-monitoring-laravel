<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings;

use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\TestCase;

final class AlertSettingsControllerTest extends TestCase
{
    public function test_index_renders_disabled_state_block(): void
    {
        $response = $this->get('/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertSee('Alerts');
        $response->assertSee('disabled', false);
        $response->assertDontSee('Mail recipients');
    }

    public function test_index_renders_scalars_and_recipients_when_enabled(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->get('/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertSee('Source name');
        $response->assertSee('Monitor URL');
        $response->assertSee('Mail recipients');
        $response->assertSee('Add recipient');
    }

    public function test_toggle_endpoint_persists_enabled_true(): void
    {
        $response = $this->post('/jobs-monitor/settings/alerts/toggle', ['enabled' => '1']);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        self::assertTrue($this->repo()->get()->isEnabled());
    }

    public function test_toggle_endpoint_persists_enabled_false(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->post('/jobs-monitor/settings/alerts/toggle', ['enabled' => '0']);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        self::assertFalse($this->repo()->get()->isEnabled());
    }

    public function test_update_persists_scalars(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->post('/jobs-monitor/settings/alerts', [
            'source_name' => 'Production',
            'monitor_url' => 'https://monitor.example.com',
        ]);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        $loaded = $this->repo()->get();
        self::assertSame('Production', $loaded->sourceName());
        self::assertSame('https://monitor.example.com', $loaded->monitorUrl()?->toString());
    }

    public function test_update_clears_scalars_when_blank(): void
    {
        $this->repo()->save(new AlertSettings(
            true,
            'Old',
            new \Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl('https://old.example.com'),
            new EmailRecipientList([]),
        ));

        $response = $this->post('/jobs-monitor/settings/alerts', [
            'source_name' => '',
            'monitor_url' => '',
        ]);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        $loaded = $this->repo()->get();
        self::assertNull($loaded->sourceName());
        self::assertNull($loaded->monitorUrl());
    }

    public function test_update_rejects_invalid_url(): void
    {
        $response = $this->post('/jobs-monitor/settings/alerts', [
            'monitor_url' => 'not-a-url',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['monitor_url']);
    }

    public function test_add_recipient_persists_single_email(): void
    {
        $response = $this->post('/jobs-monitor/settings/alerts/recipients', [
            'email' => 'ops@example.com',
        ]);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        self::assertSame(['ops@example.com'], $this->repo()->get()->mailRecipients()->toArray());
    }

    public function test_add_recipient_persists_multiple_separated_emails(): void
    {
        $response = $this->post('/jobs-monitor/settings/alerts/recipients', [
            'email' => 'ops@example.com, sre@example.com  oncall@example.com',
        ]);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        self::assertSame(
            ['ops@example.com', 'sre@example.com', 'oncall@example.com'],
            $this->repo()->get()->mailRecipients()->toArray(),
        );
    }

    public function test_add_recipient_rejects_invalid_email(): void
    {
        $response = $this->post('/jobs-monitor/settings/alerts/recipients', [
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function test_add_recipient_returns_form_error_on_duplicate(): void
    {
        $this->repo()->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com']),
        ));

        $response = $this->post('/jobs-monitor/settings/alerts/recipients', [
            'email' => 'OPS@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_remove_recipient_drops_email(): void
    {
        $this->repo()->save(new AlertSettings(
            null, null, null,
            new EmailRecipientList(['ops@example.com', 'sre@example.com']),
        ));

        $response = $this->delete('/jobs-monitor/settings/alerts/recipients/'.urlencode('ops@example.com'));

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        self::assertSame(['sre@example.com'], $this->repo()->get()->mailRecipients()->toArray());
    }

    public function test_rules_block_hidden_when_alerts_disabled(): void
    {
        $response = $this->get('/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertDontSee('Alert rules');
    }

    public function test_rules_block_visible_with_built_ins_when_enabled(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->get('/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertSee('Alert rules');
        $response->assertSee('critical_failure');
        $response->assertSee('retry_storm');
    }

    public function test_editing_query_expands_inline_form_for_target_row(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->get('/jobs-monitor/settings/alerts?editing=critical_failure');

        $response->assertOk();
        $response->assertSee('Edit critical_failure');
        $response->assertSee('name="threshold"', false);
        $response->assertSee('name="channels[]"', false);
    }

    public function test_toggle_built_in_persists_and_redirects(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure/toggle', [
            'enabled' => '0',
        ]);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        self::assertFalse($this->stateRepo()->findEnabled('critical_failure'));
    }

    public function test_toggle_built_in_returns_404_for_unknown_key(): void
    {
        $response = $this->post('/jobs-monitor/settings/alerts/built-in/unknown_rule/toggle', [
            'enabled' => '1',
        ]);

        $response->assertNotFound();
    }

    public function test_update_built_in_creates_override_rule(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure', [
            'threshold' => 42,
            'cooldown_minutes' => 30,
            'window' => '15m',
            'channels' => ['mail'],
            'enabled' => '1',
        ]);

        $response->assertRedirect('/jobs-monitor/settings/alerts');

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        $override = $this->rulesRepo()->findOverrideFor('critical_failure');
        self::assertNotNull($override);
        self::assertSame(42, $override->rule()->threshold);
        self::assertSame(['mail'], $override->rule()->channels);
    }

    public function test_update_built_in_validates_required_fields(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure', []);

        $response->assertSessionHasErrors(['threshold', 'cooldown_minutes', 'channels', 'enabled']);
    }

    public function test_update_built_in_second_save_updates_same_override(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $r1 = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure', [
            'threshold' => 10,
            'cooldown_minutes' => 15,
            'window' => '5m',
            'channels' => ['slack'],
            'enabled' => '1',
        ]);
        $r1->assertRedirect();
        self::assertCount(1, $this->rulesRepo()->all(), 'first save created override');

        $r2 = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure', [
            'threshold' => 99,
            'cooldown_minutes' => 15,
            'window' => '5m',
            'channels' => ['slack'],
            'enabled' => '1',
        ]);
        $r2->assertRedirect();

        self::assertCount(1, $this->rulesRepo()->all(), 'override rule updated in place');
        $override = $this->rulesRepo()->findOverrideFor('critical_failure');
        self::assertSame(99, $override?->rule()->threshold);
    }

    public function test_index_renders_notification_channels_card_with_all_five_channels(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->get('/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertSee('Notification channels');
        $response->assertSee('Slack');
        $response->assertSee('Mail');
        $response->assertSee('PagerDuty');
        $response->assertSee('Opsgenie');
        $response->assertSee('Webhook');
        $response->assertSee('JOBS_MONITOR_PAGERDUTY_ROUTING_KEY');
        $response->assertSee('JOBS_MONITOR_OPSGENIE_API_KEY');
        $response->assertSee('JOBS_MONITOR_WEBHOOK_URL');
    }

    public function test_channels_card_shows_configured_badge_when_env_is_set(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));
        config(['jobs-monitor.alerts.channels.pagerduty.routing_key' => 'rk-test-xyz']);

        $response = $this->get('/jobs-monitor/settings/alerts');

        $response->assertOk();
        $response->assertSee('configured');
    }

    public function test_update_built_in_accepts_incident_channels(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure', [
            'threshold' => 1,
            'cooldown_minutes' => 10,
            'window' => '15m',
            'channels' => ['slack', 'pagerduty', 'opsgenie', 'webhook'],
            'enabled' => '1',
        ]);

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        $override = $this->rulesRepo()->findOverrideFor('critical_failure');
        self::assertNotNull($override);
        self::assertSame(['slack', 'pagerduty', 'opsgenie', 'webhook'], $override->rule()->channels);
    }

    public function test_update_built_in_rejects_unknown_channel(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure', [
            'threshold' => 1,
            'cooldown_minutes' => 10,
            'window' => '15m',
            'channels' => ['discord'],
            'enabled' => '1',
        ]);

        $response->assertSessionHasErrors(['channels.0']);
    }

    public function test_reset_built_in_deletes_override_and_clears_state(): void
    {
        $this->repo()->save(new AlertSettings(true, null, null, new EmailRecipientList([])));
        $this->rulesRepo()->save(new ManagedAlertRule(
            id: null,
            key: 'built_in_override_critical_failure',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureCategory,
                window: '15m', threshold: 99, channels: ['mail'],
                cooldownMinutes: 60, triggerValue: 'critical',
            ),
            enabled: true, overridesBuiltIn: 'critical_failure', position: 0,
        ));
        $this->stateRepo()->setEnabled('critical_failure', false);

        $response = $this->post('/jobs-monitor/settings/alerts/built-in/critical_failure/reset');

        $response->assertRedirect('/jobs-monitor/settings/alerts');
        self::assertNull($this->rulesRepo()->findOverrideFor('critical_failure'));
        self::assertNull($this->stateRepo()->findEnabled('critical_failure'));
    }

    private function repo(): AlertSettingsRepository
    {
        return $this->app->make(AlertSettingsRepository::class);
    }

    private function rulesRepo(): ManagedAlertRuleRepository
    {
        return $this->app->make(ManagedAlertRuleRepository::class);
    }

    private function stateRepo(): BuiltInRuleStateRepository
    {
        return $this->app->make(BuiltInRuleStateRepository::class);
    }
}
