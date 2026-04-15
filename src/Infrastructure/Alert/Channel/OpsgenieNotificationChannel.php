<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Channel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Infrastructure\Alert\Support\AlertDeepLinker;

/**
 * Delivers alerts to Opsgenie via Alert API v2.
 *
 * `alias` field is the fingerprint (or a stable hash when fingerprint
 * is absent) so Opsgenie deduplicates repeat alerts into one incident
 * instead of spawning a new one per evaluation tick. Priority maps
 * from AlertTrigger semantics — critical categories page P1, noisy
 * noise lands on P4.
 *
 * Two regions supported: `us` → api.opsgenie.com, `eu` →
 * api.eu.opsgenie.com. Anything else falls back to US.
 *
 * Missing API key → channel is a no-op. Transport / non-2xx responses
 * throw `RuntimeException` with a sanitized message; the API key
 * never appears in logs or errors.
 */
final class OpsgenieNotificationChannel implements NotificationChannel
{
    private const ENDPOINTS = [
        'us' => 'https://api.opsgenie.com/v2/alerts',
        'eu' => 'https://api.eu.opsgenie.com/v2/alerts',
    ];

    private const DEFAULT_REGION = 'us';

    private const MESSAGE_MAX_LENGTH = 130;

    private const TIMEOUT_SECONDS = 5;

    /**
     * Trigger → Opsgenie priority (P1 = critical / wake the on-call).
     *
     * @var array<string, string>
     */
    private const PRIORITY_MAP = [
        AlertTrigger::FailureCategory->value => 'P1',
        AlertTrigger::JobClassFailureRate->value => 'P1',
        AlertTrigger::FailureGroupBurst->value => 'P1',
        AlertTrigger::ScheduledTaskFailed->value => 'P1',
        AlertTrigger::DlqSize->value => 'P2',
        AlertTrigger::FailureRate->value => 'P3',
        AlertTrigger::FailureGroupNew->value => 'P3',
        AlertTrigger::ScheduledTaskLate->value => 'P3',
        AlertTrigger::DurationAnomaly->value => 'P4',
        AlertTrigger::PartialCompletion->value => 'P4',
        AlertTrigger::ZeroProcessed->value => 'P4',
    ];

    private readonly AlertDeepLinker $deepLinker;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly LoggerInterface $logger,
        private readonly ?string $apiKey,
        private readonly string $region = self::DEFAULT_REGION,
        private readonly ?string $sourceName = null,
        ?string $monitorBaseUrl = null,
    ) {
        $this->deepLinker = new AlertDeepLinker($monitorBaseUrl);
    }

    public function name(): string
    {
        return 'opsgenie';
    }

    public function send(AlertPayload $payload): void
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            $this->logger->debug(
                '[jobs-monitor] Opsgenie channel invoked without an API key; skipping.',
                ['channel' => 'opsgenie'],
            );

            return;
        }

        try {
            $response = $this->http
                ->timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Authorization' => 'GenieKey '.$this->apiKey,
                ])
                ->asJson()
                ->post($this->endpoint(), $this->buildBody($payload));
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                sprintf('Opsgenie endpoint unreachable: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $status = $response->status();
        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new RuntimeException(
            sprintf('Opsgenie alerts API returned HTTP %d.', $status),
        );
    }

    private function endpoint(): string
    {
        return self::ENDPOINTS[$this->region] ?? self::ENDPOINTS[self::DEFAULT_REGION];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBody(AlertPayload $payload): array
    {
        $details = [
            'trigger' => $payload->trigger->value,
            'body' => $payload->body,
            'context' => $payload->context,
            'triggered_at' => $payload->triggeredAt->format(DATE_ATOM),
        ];

        $deepLink = $this->deepLinker->linkFor($payload->trigger);
        if ($deepLink !== null) {
            $details['deep_link'] = $deepLink;
        }

        return [
            'message' => $this->truncate($payload->subject, self::MESSAGE_MAX_LENGTH),
            'alias' => $this->alias($payload),
            'description' => $payload->body,
            'priority' => self::PRIORITY_MAP[$payload->trigger->value],
            'source' => $this->sourceName ?? 'jobs-monitor',
            'tags' => ['jobs-monitor', $payload->trigger->value],
            'details' => $details,
        ];
    }

    private function alias(AlertPayload $payload): string
    {
        if ($payload->fingerprint !== null && $payload->fingerprint !== '') {
            return $payload->fingerprint;
        }

        return sha1($payload->trigger->value.'|'.$payload->subject);
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 3).'...';
    }
}
