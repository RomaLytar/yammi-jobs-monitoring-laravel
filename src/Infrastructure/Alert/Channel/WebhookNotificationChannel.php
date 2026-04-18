<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Channel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Infrastructure\Alert\Support\AlertDeepLinker;
use Yammi\JobsMonitor\Infrastructure\Alert\Support\HttpStatusGuard;

/**
 * Generic signed-webhook delivery for incident-management hubs that speak
 * plain JSON (Grafana OnCall, Splunk On-Call, VictorOps, internal
 * routers, …).
 *
 * Body is signed with HMAC-SHA256 using the configured shared secret;
 * receivers verify the X-Jobs-Monitor-Signature header against the raw
 * body before trusting the call. One retry on 5xx (transient), none on
 * 4xx (configuration error). Never log or echo the secret.
 */
final class WebhookNotificationChannel implements NotificationChannel
{
    private const EVENT_TRIGGER = 'alert.trigger';

    private const EVENT_RESOLVE = 'alert.resolve';

    private const PACKAGE_USER_AGENT = 'yammi-jobs-monitor-webhook/1.0';

    private readonly AlertDeepLinker $deepLinker;

    /**
     * @param  array<string, string>  $extraHeaders  Host-supplied static
     *                                               headers merged into every
     *                                               outbound request.
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly LoggerInterface $logger,
        private readonly string $url,
        private readonly ?string $secret,
        private readonly array $extraHeaders = [],
        private readonly int $timeoutSeconds = 5,
        private readonly ?string $sourceName = null,
        ?string $monitorBaseUrl = null,
    ) {
        $this->deepLinker = new AlertDeepLinker($monitorBaseUrl);
    }

    public function name(): string
    {
        return 'webhook';
    }

    public function send(AlertPayload $payload): void
    {
        $body = $this->buildBody($payload);
        $rawBody = $this->encodeBody($body);
        $headers = $this->buildHeaders($rawBody, $body);

        $response = $this->dispatch($rawBody, $headers);
        $this->assertOk($response->status());
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function dispatch(string $rawBody, array $headers): Response
    {
        $response = $this->send_once($rawBody, $headers);

        if (! $this->shouldRetry($response->status())) {
            return $response;
        }

        $this->logger->warning(
            sprintf(
                '[jobs-monitor] Webhook returned HTTP %d, retrying once.',
                $response->status(),
            ),
            ['channel' => 'webhook', 'status' => $response->status()],
        );

        return $this->send_once($rawBody, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function send_once(string $rawBody, array $headers): Response
    {
        try {
            return $this->http
                ->withHeaders($headers)
                ->timeout($this->timeoutSeconds)
                ->withBody($rawBody, 'application/json')
                ->post($this->url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                sprintf('Webhook endpoint unreachable: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    private function shouldRetry(int $status): bool
    {
        return $status >= 500 && $status <= 599;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBody(AlertPayload $payload): array
    {
        $body = [
            'event' => $this->eventName($payload),
            'trigger' => $payload->trigger->value,
            'action' => $payload->action->value,
            'subject' => $payload->subject,
            'body' => $payload->body,
            'fingerprint' => $payload->fingerprint,
            'context' => $payload->context,
            'deep_link' => $this->deepLinker->linkFor($payload->trigger),
            'timestamp' => $payload->triggeredAt->format(DATE_ATOM),
        ];

        if ($this->sourceName !== null && $this->sourceName !== '') {
            $body['source'] = $this->sourceName;
        }

        return $body;
    }

    private function eventName(AlertPayload $payload): string
    {
        return $payload->action->isResolve() ? self::EVENT_RESOLVE : self::EVENT_TRIGGER;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function encodeBody(array $body): string
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Failed to encode webhook payload as JSON.');
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    private function buildHeaders(string $rawBody, array $body = []): array
    {
        $eventHeader = is_string($body['event'] ?? null) ? $body['event'] : self::EVENT_TRIGGER;

        $headers = $this->extraHeaders + [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => self::PACKAGE_USER_AGENT,
            'X-Jobs-Monitor-Event' => $eventHeader,
        ];

        if ($this->secret !== null && $this->secret !== '') {
            $headers['X-Jobs-Monitor-Signature'] = 'sha256='.hash_hmac('sha256', $rawBody, $this->secret);
        }

        return $headers;
    }

    private function assertOk(int $status): void
    {
        HttpStatusGuard::assertSuccess($status, 'Webhook endpoint');
    }
}
