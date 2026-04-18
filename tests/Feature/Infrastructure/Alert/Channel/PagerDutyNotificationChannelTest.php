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
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\PagerDutyNotificationChannel;
use Yammi\JobsMonitor\Tests\TestCase;

final class PagerDutyNotificationChannelTest extends TestCase
{
    public function test_name_is_pagerduty(): void
    {
        self::assertSame('pagerduty', $this->channel()->name());
    }

    public function test_posts_trigger_event_to_events_api_v2(): void
    {
        Http::fake();

        $this->channel()->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://events.pagerduty.com/v2/enqueue'
                && $request->method() === 'POST';
        });
    }

    public function test_body_contains_routing_key_and_trigger_action(): void
    {
        Http::fake();

        $this->channel(routingKey: 'rk-abc-123')->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return ($body['routing_key'] ?? null) === 'rk-abc-123'
                && ($body['event_action'] ?? null) === 'trigger';
        });
    }

    public function test_dedup_key_uses_fingerprint_when_present(): void
    {
        Http::fake();

        $this->channel()->send(new AlertPayload(
            trigger: AlertTrigger::FailureGroupBurst,
            subject: 'Burst detected',
            body: 'Many failures',
            context: [],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
            fingerprint: 'fp-deadbeef-01',
        ));

        Http::assertSent(function (Request $request): bool {
            return ($request->data()['dedup_key'] ?? null) === 'fp-deadbeef-01';
        });
    }

    public function test_dedup_key_falls_back_to_stable_hash_when_no_fingerprint(): void
    {
        Http::fake();

        $payload = $this->samplePayload();
        $this->channel()->send($payload);
        $this->channel()->send($payload);

        $seen = [];
        Http::assertSent(function (Request $request) use (&$seen): bool {
            $seen[] = $request->data()['dedup_key'] ?? null;

            return true;
        });

        self::assertCount(2, $seen);
        self::assertSame($seen[0], $seen[1]);
        self::assertNotNull($seen[0]);
        self::assertNotSame('', $seen[0]);
    }

    public function test_payload_contains_summary_severity_source_and_deep_link(): void
    {
        Http::fake();

        $this->channel(monitorBaseUrl: 'https://app.test/jobs-monitor')->send(new AlertPayload(
            trigger: AlertTrigger::DlqSize,
            subject: 'DLQ grew to 42',
            body: '42 in DLQ',
            context: ['count' => 42],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
        ));

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $pd = $body['payload'] ?? [];

            return ($pd['summary'] ?? null) === 'DLQ grew to 42'
                && in_array($pd['severity'] ?? null, ['critical', 'error', 'warning', 'info'], true)
                && ($pd['source'] ?? null) === 'jobs-monitor'
                && is_array($body['links'] ?? null)
                && isset($body['links'][0]['href'])
                && str_contains($body['links'][0]['href'], 'https://app.test/jobs-monitor/dlq');
        });
    }

    public function test_severity_mapping_sends_error_for_high_impact_triggers(): void
    {
        Http::fake();

        $payload = new AlertPayload(
            trigger: AlertTrigger::FailureCategory,
            subject: 'Critical category',
            body: 'x',
            context: [],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
        );

        $this->channel()->send($payload);

        Http::assertSent(function (Request $request): bool {
            $pd = $request->data()['payload'] ?? [];

            return ($pd['severity'] ?? null) === 'error';
        });
    }

    public function test_no_op_and_logs_debug_when_routing_key_is_missing(): void
    {
        Log::spy();
        Http::fake();

        (new PagerDutyNotificationChannel(
            http: $this->app->make(HttpFactory::class),
            logger: $this->app->make(\Psr\Log\LoggerInterface::class),
            routingKey: null,
            sourceName: null,
            monitorBaseUrl: null,
        ))->send($this->samplePayload());

        Http::assertNothingSent();
    }

    public function test_failing_http_raises_exception_without_leaking_routing_key(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response('internal', 500),
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->channel(routingKey: 'super-sensitive-rk')->send($this->samplePayload());
        } catch (RuntimeException $e) {
            self::assertStringNotContainsString('super-sensitive-rk', $e->getMessage());
            throw $e;
        }
    }

    private function channel(
        ?string $routingKey = 'test-rk',
        ?string $monitorBaseUrl = null,
        ?string $sourceName = null,
    ): PagerDutyNotificationChannel {
        return new PagerDutyNotificationChannel(
            http: $this->app->make(HttpFactory::class),
            logger: $this->app->make(\Psr\Log\LoggerInterface::class),
            routingKey: $routingKey,
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
