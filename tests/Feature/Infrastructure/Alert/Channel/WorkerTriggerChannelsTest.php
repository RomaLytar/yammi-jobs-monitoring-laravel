<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Alert\Channel;

use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LoggerInterface;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\MailNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\OpsgenieNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\PagerDutyNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\SlackNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\WebhookNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Mail\JobsMonitorAlertMail;
use Yammi\JobsMonitor\Tests\TestCase;

final class WorkerTriggerChannelsTest extends TestCase
{
    public function test_slack_renders_worker_silent_with_dedicated_emoji_and_deep_link(): void
    {
        Http::fake();

        $this->slack()->send($this->workerSilentPayload());

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $header = $body['blocks'][0]['text']['text'] ?? '';
            $actions = $this->findActions($body['blocks'] ?? []);

            return str_starts_with($header, '🔕')
                && $actions !== null
                && str_ends_with(($actions['elements'][0]['url'] ?? ''), '/workers#workers-silent');
        });
    }

    public function test_slack_renders_resolve_prefix_in_header(): void
    {
        Http::fake();

        $this->slack()->send($this->workerSilentResolvePayload());

        Http::assertSent(function (Request $request): bool {
            $header = $request->data()['blocks'][0]['text']['text'] ?? '';

            return str_starts_with($header, '✅')
                && str_contains($header, '[Resolved]');
        });
    }

    public function test_pagerduty_sends_resolve_event_action_when_payload_is_resolve(): void
    {
        Http::fake();

        $this->pagerduty()->send($this->workerSilentResolvePayload());

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return ($body['event_action'] ?? null) === 'resolve'
                && ! isset($body['payload'])
                && ! empty($body['dedup_key']);
        });
    }

    public function test_pagerduty_maps_worker_silent_to_error_severity(): void
    {
        Http::fake();

        $this->pagerduty()->send($this->workerSilentPayload());

        Http::assertSent(function (Request $request): bool {
            return ($request->data()['payload']['severity'] ?? null) === 'error';
        });
    }

    public function test_opsgenie_closes_alert_on_resolve(): void
    {
        Http::fake();

        $this->opsgenie()->send($this->workerSilentResolvePayload());

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/close')
                && str_contains($request->url(), 'identifierType=alias');
        });
    }

    public function test_opsgenie_worker_silent_is_priority_p1(): void
    {
        Http::fake();

        $this->opsgenie()->send($this->workerSilentPayload());

        Http::assertSent(function (Request $request): bool {
            return ($request->data()['priority'] ?? null) === 'P1';
        });
    }

    public function test_opsgenie_worker_underprovisioned_is_priority_p2(): void
    {
        Http::fake();

        $this->opsgenie()->send(new AlertPayload(
            trigger: AlertTrigger::WorkerUnderprovisioned,
            subject: 'Queue redis:default underprovisioned',
            body: 'observed 1/2',
            context: ['queue_key' => 'redis:default', 'observed' => 1, 'expected' => 2],
            triggeredAt: new DateTimeImmutable('2026-04-16T10:00:00Z'),
            fingerprint: 'worker_underprovisioned:redis:default',
        ));

        Http::assertSent(function (Request $request): bool {
            return ($request->data()['priority'] ?? null) === 'P2';
        });
    }

    public function test_webhook_emits_resolve_event_and_header(): void
    {
        Http::fake();

        $this->webhook()->send($this->workerSilentResolvePayload());

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && ($body['event'] ?? null) === 'alert.resolve'
                && ($body['action'] ?? null) === 'resolve'
                && ($request->header('X-Jobs-Monitor-Event')[0] ?? null) === 'alert.resolve';
        });
    }

    public function test_webhook_emits_worker_silent_deep_link_and_trigger_event(): void
    {
        Http::fake();

        $this->webhook()->send($this->workerSilentPayload());

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && ($body['event'] ?? null) === 'alert.trigger'
                && ($body['action'] ?? null) === 'trigger'
                && ($body['trigger'] ?? null) === 'worker_silent'
                && is_string($body['deep_link'] ?? null)
                && str_ends_with((string) $body['deep_link'], '/workers#workers-silent');
        });
    }

    public function test_mail_prefixes_subject_with_resolved_marker(): void
    {
        Mail::fake();

        $channel = new MailNotificationChannel(
            $this->app->make(Mailer::class),
            ['ops@example.com'],
            'AcmeProd',
            'https://app.test/jobs-monitor',
        );

        $channel->send($this->workerSilentResolvePayload());

        Mail::assertSent(JobsMonitorAlertMail::class, function (JobsMonitorAlertMail $mail): bool {
            $mail->build();
            $subject = $mail->subject ?? '';

            return str_contains($subject, '[Resolved]')
                && str_contains($subject, 'Worker silent');
        });
    }

    private function workerSilentPayload(): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::WorkerSilent,
            subject: 'Worker silent: host-a:1234',
            body: 'Last seen 6m ago (threshold 2m)',
            context: ['worker_id' => 'host-a:1234', 'queue_key' => 'redis:default'],
            triggeredAt: new DateTimeImmutable('2026-04-16T10:00:00Z'),
            fingerprint: 'worker_silent:host-a:1234',
        );
    }

    private function workerSilentResolvePayload(): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::WorkerSilent,
            subject: 'Worker silent: host-a:1234',
            body: 'Heartbeat resumed',
            context: ['worker_id' => 'host-a:1234'],
            triggeredAt: new DateTimeImmutable('2026-04-16T10:05:00Z'),
            fingerprint: 'worker_silent:host-a:1234',
            action: AlertAction::Resolve,
        );
    }

    private function slack(): SlackNotificationChannel
    {
        return new SlackNotificationChannel(
            $this->app->make(HttpFactory::class),
            'https://hooks.slack.com/services/X/Y/Z',
            null,
            'AcmeProd',
            'https://app.test/jobs-monitor',
        );
    }

    private function pagerduty(): PagerDutyNotificationChannel
    {
        return new PagerDutyNotificationChannel(
            http: $this->app->make(HttpFactory::class),
            logger: $this->app->make(LoggerInterface::class),
            routingKey: 'test-rk',
            sourceName: 'AcmeProd',
            monitorBaseUrl: 'https://app.test/jobs-monitor',
        );
    }

    private function opsgenie(): OpsgenieNotificationChannel
    {
        return new OpsgenieNotificationChannel(
            http: $this->app->make(HttpFactory::class),
            logger: $this->app->make(LoggerInterface::class),
            apiKey: 'test-key',
            region: 'us',
            sourceName: 'AcmeProd',
            monitorBaseUrl: 'https://app.test/jobs-monitor',
        );
    }

    private function webhook(): WebhookNotificationChannel
    {
        return new WebhookNotificationChannel(
            $this->app->make(HttpFactory::class),
            $this->app->make(LoggerInterface::class),
            'https://hooks.example.test/alert',
            'shhh',
            [],
            5,
            'AcmeProd',
            'https://app.test/jobs-monitor',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private function findActions(array $blocks): ?array
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'actions') {
                return $block;
            }
        }

        return null;
    }
}
