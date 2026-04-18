<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Alert\Channel;

use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\WebhookNotificationChannel;
use Yammi\JobsMonitor\Tests\TestCase;

final class WebhookNotificationChannelTest extends TestCase
{
    public function test_name_is_webhook(): void
    {
        $channel = $this->channel();

        self::assertSame('webhook', $channel->name());
    }

    public function test_posts_signed_json_to_configured_url(): void
    {
        Http::fake();

        $this->channel(secret: 'top-secret')->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://webhook.test/incidents') {
                return false;
            }
            if ($request->method() !== 'POST') {
                return false;
            }

            $header = $request->header('X-Jobs-Monitor-Signature')[0] ?? null;
            if (! is_string($header) || $header === '') {
                return false;
            }

            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'top-secret');

            return hash_equals($expected, $header);
        });
    }

    public function test_sends_trigger_event_header(): void
    {
        Http::fake();

        $this->channel()->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return ($request->header('X-Jobs-Monitor-Event')[0] ?? null) === 'alert.trigger';
        });
    }

    public function test_body_contains_core_alert_fields(): void
    {
        Http::fake();

        $this->channel(monitorBaseUrl: 'https://app.test/jobs-monitor')->send(new AlertPayload(
            trigger: AlertTrigger::DlqSize,
            subject: 'DLQ grew to 42',
            body: '42 failures resting in DLQ',
            context: ['count' => 42],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
            fingerprint: 'abc123deadbeef',
        ));

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            if (! is_array($body)) {
                return false;
            }

            return ($body['event'] ?? null) === 'alert.trigger'
                && ($body['trigger'] ?? null) === 'dlq_size'
                && ($body['subject'] ?? null) === 'DLQ grew to 42'
                && ($body['fingerprint'] ?? null) === 'abc123deadbeef'
                && ($body['deep_link'] ?? null) === 'https://app.test/jobs-monitor/dlq'
                && is_string($body['timestamp'] ?? null);
        });
    }

    public function test_merges_host_supplied_headers(): void
    {
        Http::fake();

        $this->channel(extraHeaders: ['X-Tenant' => 'acme', 'Authorization' => 'Bearer xyz'])
            ->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return ($request->header('X-Tenant')[0] ?? null) === 'acme'
                && ($request->header('Authorization')[0] ?? null) === 'Bearer xyz';
        });
    }

    public function test_retries_once_on_5xx_then_succeeds(): void
    {
        Http::fakeSequence('webhook.test/*')
            ->push('boom', 502)
            ->push('', 204);

        $this->channel()->send($this->samplePayload());

        Http::assertSentCount(2);
    }

    public function test_does_not_retry_on_4xx_and_throws(): void
    {
        Http::fake([
            'webhook.test/*' => Http::response('bad request', 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Webhook endpoint returned HTTP 400');

        try {
            $this->channel()->send($this->samplePayload());
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_secret_is_not_present_in_exception_message(): void
    {
        Http::fake([
            'webhook.test/*' => Http::response('nope', 500),
        ]);

        $caught = null;
        try {
            $this->channel(secret: 'super-sensitive-secret')->send($this->samplePayload());
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringNotContainsString('super-sensitive-secret', $caught->getMessage());
    }

    public function test_omits_signature_header_when_no_secret_configured(): void
    {
        Http::fake();

        $this->channel(secret: null)->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return $request->header('X-Jobs-Monitor-Signature') === [];
        });
    }

    public function test_sample_verifier_snippet_validates_signature(): void
    {
        // A host receiver would compute HMAC over the raw body and compare in
        // constant time. This test proves the shipped signature can be
        // verified by that reference snippet.
        Http::fake();

        $this->channel(secret: 'shared-key')->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            $header = $request->header('X-Jobs-Monitor-Signature')[0] ?? '';
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'shared-key');

            return hash_equals($expected, $header);
        });
    }

    public function test_logs_debug_on_5xx_retry(): void
    {
        Log::spy();

        Http::fakeSequence('webhook.test/*')
            ->push('fail-1', 500)
            ->push('ok', 200);

        $this->channel()->send($this->samplePayload());

        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    /**
     * @param  array<string, string>  $extraHeaders
     */
    private function channel(
        ?string $secret = 'default-secret',
        array $extraHeaders = [],
        ?string $monitorBaseUrl = null,
        ?string $sourceName = null,
    ): WebhookNotificationChannel {
        return new WebhookNotificationChannel(
            http: $this->app->make(HttpFactory::class),
            logger: $this->app->make(\Psr\Log\LoggerInterface::class),
            url: 'https://webhook.test/incidents',
            secret: $secret,
            extraHeaders: $extraHeaders,
            timeoutSeconds: 5,
            sourceName: $sourceName,
            monitorBaseUrl: $monitorBaseUrl,
        );
    }

    private function samplePayload(): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::FailureRate,
            subject: 'Failure rate spike',
            body: '12 failures in the last 5m',
            context: ['count' => 12, 'window' => '5m'],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
        );
    }
}
