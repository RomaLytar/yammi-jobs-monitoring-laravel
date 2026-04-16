<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Channel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Support\HttpStatusGuard;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\FailureSample;

/**
 * Delivers an alert to Slack via incoming webhook.
 *
 * Renders the payload as Block Kit with a header, a summary, a
 * "Recent failures" list whose items link straight to the monitor
 * detail page, and a primary button pointing to the dashboard. The
 * site label (app name + environment) is shown in the context line
 * so on-call sees which system fired.
 *
 * Hosts without a monitor URL configured get a compact version
 * without the link blocks.
 */
final class SlackNotificationChannel implements NotificationChannel
{
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $webhookUrl,
        private readonly ?string $signingSecret,
        private readonly ?string $sourceName = null,
        private readonly ?string $monitorBaseUrl = null,
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
        $blocks = [
            $this->headerBlock($payload),
            $this->summaryBlock($payload),
        ];

        $fingerprintsBlock = $this->fingerprintsBlock($payload);
        if ($fingerprintsBlock !== null) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = $fingerprintsBlock;
        }

        $failuresBlock = $this->recentFailuresBlock($payload);
        if ($failuresBlock !== null) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = $failuresBlock;
        }

        $actionsBlock = $this->actionsBlock($payload);
        if ($actionsBlock !== null) {
            $blocks[] = $actionsBlock;
        }

        $blocks[] = $this->contextBlock($payload);

        return [
            'text' => $this->plainFallback($payload),
            'blocks' => $blocks,
        ];
    }

    /**
     * Lists the fingerprints contained in a FailureGroupNew payload so
     * operators can see *which* groups are new without opening the UI.
     *
     * @return array<string, mixed>|null
     */
    private function fingerprintsBlock(AlertPayload $payload): ?array
    {
        if ($payload->trigger === AlertTrigger::FailureGroupNew) {
            return $this->newFingerprintsBlock($payload);
        }

        if ($payload->trigger === AlertTrigger::FailureGroupBurst) {
            return $this->burstFingerprintBlock($payload);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function newFingerprintsBlock(AlertPayload $payload): ?array
    {
        /** @var array<int, mixed> $fingerprints */
        $fingerprints = (array) ($payload->context['fingerprints'] ?? []);
        if ($fingerprints === []) {
            return null;
        }

        $lines = ['*New fingerprints:*'];
        foreach ($fingerprints as $hash) {
            if (is_string($hash) && $hash !== '') {
                $lines[] = sprintf('• `%s`', $hash);
            }
        }

        return [
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => implode("\n", $lines)],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function burstFingerprintBlock(AlertPayload $payload): ?array
    {
        if ($payload->fingerprint === null) {
            return null;
        }

        $lines = [sprintf('*Fingerprint:* `%s`', $payload->fingerprint)];

        $excClass = $payload->context['sample_exception_class'] ?? null;
        if (is_string($excClass) && $excClass !== '') {
            $lines[] = sprintf('*Exception:* `%s`', $excClass);
        }

        $sample = $payload->context['sample_message'] ?? null;
        if (is_string($sample) && $sample !== '') {
            $lines[] = sprintf('*Sample:* %s', $sample);
        }

        $occurrences = $payload->context['occurrences'] ?? null;
        if (is_int($occurrences)) {
            $lines[] = sprintf('*Total occurrences (all-time):* %d', $occurrences);
        }

        return [
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => implode("\n", $lines)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function headerBlock(AlertPayload $payload): array
    {
        return [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => $this->headerText($payload),
            ],
        ];
    }

    private function headerText(AlertPayload $payload): string
    {
        if ($payload->action->isResolve()) {
            return sprintf('✅  [Resolved] %s', $payload->subject);
        }

        // Modern, minimal set — clean circles for severity, skull for DLQ.
        // No ":warning:" (yellow triangle) or ":rotating_light:" — dated.
        $emoji = match ($payload->trigger) {
            AlertTrigger::FailureCategory => '🔴',
            AlertTrigger::JobClassFailureRate => '🔴',
            AlertTrigger::DlqSize => '💀',
            AlertTrigger::FailureRate => '📈',
            AlertTrigger::FailureGroupNew => '🆕',
            AlertTrigger::FailureGroupBurst => '🔥',
            AlertTrigger::ScheduledTaskFailed => '⛔',
            AlertTrigger::ScheduledTaskLate => '⏳',
            AlertTrigger::DurationAnomaly => '📉',
            AlertTrigger::PartialCompletion => '⚠',
            AlertTrigger::ZeroProcessed => '🕳',
            AlertTrigger::WorkerSilent => '🔕',
            AlertTrigger::WorkerUnderprovisioned => '👥',
        };

        return sprintf('%s  %s', $emoji, $payload->subject);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryBlock(AlertPayload $payload): array
    {
        $lines = [];
        if ($this->sourceName !== null && $this->sourceName !== '') {
            $lines[] = sprintf('*Site:* %s', $this->sourceName);
        }
        $lines[] = $payload->body;

        return [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => implode("\n", $lines),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recentFailuresBlock(AlertPayload $payload): ?array
    {
        if ($payload->recentFailures === []) {
            return null;
        }

        $lines = ['*Recent failures:*'];
        foreach ($payload->recentFailures as $sample) {
            $lines[] = $this->renderFailureLine($sample);
        }

        $more = $this->moreHint($payload);
        if ($more !== null) {
            $lines[] = $more;
        }

        return [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => implode("\n", $lines),
            ],
        ];
    }

    private function moreHint(AlertPayload $payload): ?string
    {
        $total = $payload->context['count'] ?? null;
        $shown = count($payload->recentFailures);

        if (! is_int($total) || $total <= $shown) {
            return null;
        }

        $link = $this->monitorBaseUrl !== null
            ? sprintf('<%s|open dashboard>', $this->monitorBaseUrl)
            : 'open dashboard';

        return sprintf('_+ %d more — %s_', $total - $shown, $link);
    }

    private function renderFailureLine(FailureSample $sample): string
    {
        $label = sprintf('%s #%d', $sample->shortClass(), $sample->attempt);
        $excerpt = $sample->shortException();

        if ($this->monitorBaseUrl !== null) {
            $label = sprintf('<%s|%s>', $this->detailUrl($sample), $label);
        }

        return $excerpt === null || $excerpt === ''
            ? sprintf('• %s', $label)
            : sprintf('• %s — %s', $label, $excerpt);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actionsBlock(AlertPayload $payload): ?array
    {
        if ($this->monitorBaseUrl === null) {
            return null;
        }

        $base = rtrim($this->monitorBaseUrl, '/');

        // Each trigger gets a primary deep-link straight to the page that
        // shows the matching rows, so the operator never has to start at
        // the dashboard and hunt. "Open dashboard" stays as a secondary
        // fallback for general context.
        [$primaryLabel, $primaryPath, $secondary] = match ($payload->trigger) {
            AlertTrigger::FailureGroupNew, AlertTrigger::FailureGroupBurst => ['Open failure groups', '/failures', null],
            AlertTrigger::DlqSize => ['Open DLQ', '/dlq', null],
            AlertTrigger::ScheduledTaskFailed => ['Open scheduled tasks (failed)', '/scheduled?status=failed', null],
            AlertTrigger::ScheduledTaskLate => ['Open scheduled tasks (late)', '/scheduled?status=late', null],
            AlertTrigger::DurationAnomaly => ['Open duration anomalies', '/anomalies', null],
            AlertTrigger::PartialCompletion => ['Open partial completions', '/anomalies#anomalies-partial', null],
            AlertTrigger::ZeroProcessed => ['Open silent successes', '/anomalies#anomalies-silent', null],
            AlertTrigger::WorkerSilent => ['Open silent workers', '/workers#workers-silent', null],
            AlertTrigger::WorkerUnderprovisioned => ['Open queue coverage', '/workers#workers-coverage', null],
            AlertTrigger::FailureCategory, AlertTrigger::JobClassFailureRate, AlertTrigger::FailureRate => ['Open dashboard', '', '/dlq'],
        };

        $elements = [[
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $primaryLabel],
            'url' => $base.$primaryPath,
            'style' => 'primary',
        ]];

        if ($secondary !== null) {
            $elements[] = [
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => 'Open DLQ'],
                'url' => $base.$secondary,
                'style' => 'danger',
            ];
        }

        return ['type' => 'actions', 'elements' => $elements];
    }

    /**
     * @return array<string, mixed>
     */
    private function contextBlock(AlertPayload $payload): array
    {
        $parts = [];
        if ($this->sourceName !== null && $this->sourceName !== '') {
            $parts[] = $this->sourceName;
        }
        $parts[] = sprintf('triggered at %s', $payload->triggeredAt->format('Y-m-d H:i:s T'));

        return [
            'type' => 'context',
            'elements' => [
                ['type' => 'mrkdwn', 'text' => '_'.implode(' • ', $parts).'_'],
            ],
        ];
    }

    private function detailUrl(FailureSample $sample): string
    {
        return sprintf(
            '%s/%s/%d',
            rtrim((string) $this->monitorBaseUrl, '/'),
            $sample->uuid,
            $sample->attempt,
        );
    }

    private function plainFallback(AlertPayload $payload): string
    {
        if ($this->sourceName === null || $this->sourceName === '') {
            return $payload->subject;
        }

        return sprintf('[%s] %s', $this->sourceName, $payload->subject);
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
        HttpStatusGuard::assertSuccess($statusCode, 'Slack webhook');
    }
}
