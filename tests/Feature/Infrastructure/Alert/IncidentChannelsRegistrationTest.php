<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Alert;

use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Yammi\JobsMonitor\Application\Action\SendAlertAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Tests\TestCase;

/**
 * Registration-level proof that the three new incident channels are
 * resolved from config, plug into SendAlertAction, and fire correctly
 * alongside the pre-existing Slack / Mail channels. Covers the
 * fail-closed property: one channel failing does not block the others.
 */
final class IncidentChannelsRegistrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('jobs-monitor.alerts.enabled', true);
        $app['config']->set('jobs-monitor.alerts.channels.pagerduty.routing_key', 'rk-test');
        $app['config']->set('jobs-monitor.alerts.channels.opsgenie.api_key', 'ak-test');
        $app['config']->set('jobs-monitor.alerts.channels.opsgenie.region', 'us');
        $app['config']->set('jobs-monitor.alerts.channels.webhook.url', 'https://webhook.test/hook');
        $app['config']->set('jobs-monitor.alerts.channels.webhook.secret', 'shared-secret');
    }

    public function test_all_three_new_channels_fire_when_configured(): void
    {
        Http::fake();

        /** @var SendAlertAction $action */
        $action = $this->app->make(SendAlertAction::class);

        $action($this->payload(), ['pagerduty', 'opsgenie', 'webhook']);

        Http::assertSentCount(3);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://events.pagerduty.com/'));
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.opsgenie.com/'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://webhook.test/hook');
    }

    public function test_failing_channel_does_not_block_siblings(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response('server down', 500),
            'api.opsgenie.com/*' => Http::response('', 202),
            'webhook.test/*' => Http::response('', 204),
        ]);

        /** @var SendAlertAction $action */
        $action = $this->app->make(SendAlertAction::class);

        $action($this->payload(), ['pagerduty', 'opsgenie', 'webhook']);

        // All three were attempted even though PagerDuty 500'd (SendAlertAction
        // catches the exception per channel and keeps going).
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://events.pagerduty.com/'));
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.opsgenie.com/'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://webhook.test/hook');
    }

    private function payload(): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::DlqSize,
            subject: 'DLQ grew to 42',
            body: '42 failures resting in DLQ',
            context: ['count' => 42],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
            fingerprint: 'abc123deadbeef',
        );
    }
}
