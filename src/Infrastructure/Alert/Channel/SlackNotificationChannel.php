<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Channel;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

/**
 * Delivers an alert to Slack via incoming webhook.
 *
 * The payload uses Slack's Block Kit format for nicer rendering; the
 * top-level "text" field is the plain-text fallback for clients that
 * ignore blocks. When a signing secret is configured, every request
 * carries an X-JobsMonitor-Signature header so receivers can verify
 * the webhook really came from this package.
 */
final class SlackNotificationChannel implements NotificationChannel
{
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $webhookUrl,
        private readonly ?string $signingSecret,
    ) {}

    public function name(): string
    {
        return 'slack';
    }

    public function send(AlertPayload $payload): void
    {
        $body = $this->buildBody($payload);
        $headers = $this->buildHeaders($body);

        $response = $this->http
            ->withHeaders($headers)
            ->timeout(self::TIMEOUT_SECONDS)
            ->post($this->webhookUrl, $body);

        $this->assertOk($response->status());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBody(AlertPayload $payload): array
    {
        return [
            'text' => $payload->subject,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => $payload->subject,
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $payload->body,
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf(
                                '_triggered at %s_',
                                $payload->triggeredAt->format('Y-m-d H:i:s T'),
                            ),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    private function buildHeaders(array $body): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($this->signingSecret === null) {
            return $headers;
        }

        $canonical = (string) json_encode($body);
        $signature = 'sha256='.hash_hmac('sha256', $canonical, $this->signingSecret);

        return $headers + ['X-JobsMonitor-Signature' => $signature];
    }

    private function assertOk(int $statusCode): void
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        throw new RuntimeException(sprintf('Slack webhook returned HTTP %d.', $statusCode));
    }
}
