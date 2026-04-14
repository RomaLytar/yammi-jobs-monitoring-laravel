<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Alert\Channel;

use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\SlackNotificationChannel;
use Yammi\JobsMonitor\Tests\TestCase;

final class SlackNotificationChannelTest extends TestCase
{
    public function test_name_is_slack(): void
    {
        $channel = new SlackNotificationChannel($this->http(), 'https://hooks.slack.test/x', null);

        self::assertSame('slack', $channel->name());
    }

    public function test_posts_json_payload_to_configured_webhook(): void
    {
        Http::fake();

        $channel = new SlackNotificationChannel($this->http(), 'https://hooks.slack.test/abc', null);
        $channel->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'https://hooks.slack.test/abc'
                && $request->method() === 'POST'
                && str_contains((string) ($body['text'] ?? ''), 'Failure rate spike')
                && isset($body['blocks'])
                && is_array($body['blocks']);
        });
    }

    public function test_includes_signature_header_when_secret_is_configured(): void
    {
        Http::fake();

        $channel = new SlackNotificationChannel($this->http(), 'https://hooks.slack.test/x', 'shh-secret');
        $channel->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            $signature = $request->header('X-JobsMonitor-Signature')[0] ?? null;
            if ($signature === null) {
                return false;
            }

            // Should be sha256= prefix + 64 hex chars
            return preg_match('/^sha256=[0-9a-f]{64}$/', $signature) === 1;
        });
    }

    public function test_omits_signature_header_when_secret_is_not_configured(): void
    {
        Http::fake();

        $channel = new SlackNotificationChannel($this->http(), 'https://hooks.slack.test/x', null);
        $channel->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return $request->header('X-JobsMonitor-Signature') === [];
        });
    }

    public function test_failing_http_response_raises_exception(): void
    {
        Http::fake([
            'hooks.slack.test/*' => Http::response('server down', 500),
        ]);

        $channel = new SlackNotificationChannel($this->http(), 'https://hooks.slack.test/x', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slack webhook returned HTTP 500');

        $channel->send($this->samplePayload());
    }

    public function test_handles_every_alert_trigger_without_unhandled_match(): void
    {
        Http::fake();

        $channel = new SlackNotificationChannel($this->http(), 'https://hooks.slack.test/x', null);

        foreach (AlertTrigger::cases() as $trigger) {
            $channel->send(new AlertPayload(
                trigger: $trigger,
                subject: 'Test '.$trigger->value,
                body: 'body for '.$trigger->value,
                context: ['count' => 1, 'window' => '5m'],
                triggeredAt: new DateTimeImmutable('2026-04-13T12:00:00Z'),
            ));
        }

        Http::assertSentCount(count(AlertTrigger::cases()));
    }

    private function samplePayload(): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::FailureRate,
            subject: 'Failure rate spike',
            body: '12 failures in the last 5m',
            context: ['count' => 12, 'window' => '5m'],
            triggeredAt: new DateTimeImmutable('2026-04-13T12:00:00Z'),
        );
    }

    private function http(): HttpFactory
    {
        return $this->app->make(HttpFactory::class);
    }
}
