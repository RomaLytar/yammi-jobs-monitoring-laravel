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
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\OpsgenieNotificationChannel;
use Yammi\JobsMonitor\Tests\TestCase;

final class OpsgenieNotificationChannelTest extends TestCase
{
    public function test_name_is_opsgenie(): void
    {
        self::assertSame('opsgenie', $this->channel()->name());
    }

    public function test_us_region_posts_to_us_endpoint(): void
    {
        Http::fake();

        $this->channel(region: 'us')->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.opsgenie.com/v2/alerts'
                && $request->method() === 'POST';
        });
    }

    public function test_eu_region_posts_to_eu_endpoint(): void
    {
        Http::fake();

        $this->channel(region: 'eu')->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.eu.opsgenie.com/v2/alerts';
        });
    }

    public function test_sends_genie_key_authorization_header(): void
    {
        Http::fake();

        $this->channel(apiKey: 'ak-abc-123')->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            $auth = $request->header('Authorization')[0] ?? null;

            return $auth === 'GenieKey ak-abc-123';
        });
    }

    public function test_alias_uses_fingerprint_for_dedup(): void
    {
        Http::fake();

        $this->channel()->send(new AlertPayload(
            trigger: AlertTrigger::FailureGroupBurst,
            subject: 'Burst',
            body: 'many',
            context: [],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
            fingerprint: 'fp-xyz-42',
        ));

        Http::assertSent(function (Request $request): bool {
            return ($request->data()['alias'] ?? null) === 'fp-xyz-42';
        });
    }

    public function test_body_contains_message_priority_source_details(): void
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

            return ($body['message'] ?? null) === 'DLQ grew to 42'
                && in_array($body['priority'] ?? null, ['P1', 'P2', 'P3', 'P4', 'P5'], true)
                && ($body['source'] ?? null) === 'jobs-monitor'
                && is_array($body['details'] ?? null)
                && str_contains((string) ($body['details']['deep_link'] ?? ''), 'https://app.test/jobs-monitor/dlq');
        });
    }

    public function test_priority_mapping_sends_p1_for_critical_triggers(): void
    {
        Http::fake();

        $this->channel()->send(new AlertPayload(
            trigger: AlertTrigger::FailureCategory,
            subject: 'Critical',
            body: 'x',
            context: [],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
        ));

        Http::assertSent(function (Request $request): bool {
            return ($request->data()['priority'] ?? null) === 'P1';
        });
    }

    public function test_message_is_truncated_to_130_chars_per_opsgenie_limit(): void
    {
        Http::fake();

        $longSubject = str_repeat('A', 200);

        $this->channel()->send(new AlertPayload(
            trigger: AlertTrigger::FailureRate,
            subject: $longSubject,
            body: 'y',
            context: [],
            triggeredAt: new DateTimeImmutable('2026-04-16T09:00:00Z'),
        ));

        Http::assertSent(function (Request $request): bool {
            $message = (string) ($request->data()['message'] ?? '');

            return strlen($message) <= 130;
        });
    }

    public function test_no_op_and_logs_debug_when_api_key_missing(): void
    {
        Log::spy();
        Http::fake();

        $this->channel(apiKey: null)->send($this->samplePayload());

        Http::assertNothingSent();
    }

    public function test_failing_http_raises_without_leaking_api_key(): void
    {
        Http::fake([
            'api.opsgenie.com/*' => Http::response('server down', 500),
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->channel(apiKey: 'super-sensitive-ak')->send($this->samplePayload());
        } catch (RuntimeException $e) {
            self::assertStringNotContainsString('super-sensitive-ak', $e->getMessage());
            throw $e;
        }
    }

    public function test_invalid_region_defaults_to_us_endpoint(): void
    {
        Http::fake();

        $this->channel(region: 'invalid-xx')->send($this->samplePayload());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.opsgenie.com/v2/alerts';
        });
    }

    private function channel(
        ?string $apiKey = 'test-ak',
        string $region = 'us',
        ?string $monitorBaseUrl = null,
        ?string $sourceName = null,
    ): OpsgenieNotificationChannel {
        return new OpsgenieNotificationChannel(
            http: $this->app->make(HttpFactory::class),
            logger: $this->app->make(\Psr\Log\LoggerInterface::class),
            apiKey: $apiKey,
            region: $region,
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
