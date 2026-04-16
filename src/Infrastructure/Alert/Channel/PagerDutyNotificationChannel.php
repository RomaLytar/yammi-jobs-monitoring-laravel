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
 * Delivers alerts to PagerDuty via Events API v2.
 *
 * Sends a `trigger` event per `AlertPayload`. `dedup_key` is the alert
 * fingerprint when present, otherwise a stable SHA-1 of trigger+subject
 * so PagerDuty collapses repeats of the same alert into a single
 * incident instead of paging on every evaluation tick.
 *
 * Missing routing key → channel is a no-op (one debug log) so hosts can
 * leave the channel registered but unconfigured without crashing.
 * Transport / non-2xx responses throw `RuntimeException` with a
 * sanitized message; the routing key never appears in logs or errors.
 */
final class PagerDutyNotificationChannel implements NotificationChannel
{
    private const ENDPOINT = 'https://events.pagerduty.com/v2/enqueue';

    private const EVENT_ACTION_TRIGGER = 'trigger';

    private const EVENT_ACTION_RESOLVE = 'resolve';

    private const TIMEOUT_SECONDS = 5;

    private readonly AlertDeepLinker $deepLinker;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly LoggerInterface $logger,
        private readonly ?string $routingKey,
        private readonly ?string $sourceName = null,
        ?string $monitorBaseUrl = null,
    ) {
        $this->deepLinker = new AlertDeepLinker($monitorBaseUrl);
    }

    public function name(): string
    {
        return 'pagerduty';
    }

    public function send(AlertPayload $payload): void
    {
        if ($this->routingKey === null || $this->routingKey === '') {
            $this->logger->debug(
                '[jobs-monitor] PagerDuty channel invoked without a routing key; skipping.',
                ['channel' => 'pagerduty'],
            );

            return;
        }

        try {
            $response = $this->http
                ->timeout(self::TIMEOUT_SECONDS)
                ->asJson()
                ->post(self::ENDPOINT, $this->buildBody($payload));
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                sprintf('PagerDuty endpoint unreachable: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $status = $response->status();
        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new RuntimeException(
            sprintf('PagerDuty events API returned HTTP %d.', $status),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBody(AlertPayload $payload): array
    {
        $deepLink = $this->deepLinker->linkFor($payload->trigger);

        // Resolve events only need routing_key + action + dedup_key so
        // PagerDuty can find the matching open incident. The optional
        // `payload` on a resolve is ignored by the API.
        if ($payload->action->isResolve()) {
            return [
                'routing_key' => $this->routingKey,
                'event_action' => self::EVENT_ACTION_RESOLVE,
                'dedup_key' => $this->dedupKey($payload),
            ];
        }

        $body = [
            'routing_key' => $this->routingKey,
            'event_action' => self::EVENT_ACTION_TRIGGER,
            'dedup_key' => $this->dedupKey($payload),
            'payload' => [
                'summary' => $payload->subject,
                'severity' => $this->severityFor($payload->trigger),
                'source' => $this->sourceName ?? 'jobs-monitor',
                'component' => $payload->trigger->value,
                'custom_details' => [
                    'body' => $payload->body,
                    'context' => $payload->context,
                    'triggered_at' => $payload->triggeredAt->format(DATE_ATOM),
                ],
            ],
        ];

        if ($deepLink !== null) {
            $body['links'] = [[
                'href' => $deepLink,
                'text' => 'Open monitor',
            ]];
        }

        return $body;
    }

    /**
     * Trigger → PagerDuty severity. Exhaustive `match` so adding a new
     * AlertTrigger case without a mapping here fails PHPStan — that is
     * exactly the safety net we want.
     */
    private function severityFor(AlertTrigger $trigger): string
    {
        return match ($trigger) {
            AlertTrigger::FailureCategory,
            AlertTrigger::JobClassFailureRate,
            AlertTrigger::DlqSize,
            AlertTrigger::FailureGroupBurst,
            AlertTrigger::ScheduledTaskFailed,
            AlertTrigger::WorkerSilent,
            AlertTrigger::WorkerUnderprovisioned => 'error',
            AlertTrigger::FailureRate,
            AlertTrigger::FailureGroupNew,
            AlertTrigger::ScheduledTaskLate,
            AlertTrigger::DurationAnomaly,
            AlertTrigger::PartialCompletion,
            AlertTrigger::ZeroProcessed => 'warning',
        };
    }

    private function dedupKey(AlertPayload $payload): string
    {
        if ($payload->fingerprint !== null && $payload->fingerprint !== '') {
            return $payload->fingerprint;
        }

        // Stable hash so repeated evaluations of the same rule attach to
        // the existing PD incident rather than opening a new one every
        // tick. Hour bucket would also work; trigger+subject is enough.
        return sha1($payload->trigger->value.'|'.$payload->subject);
    }
}
