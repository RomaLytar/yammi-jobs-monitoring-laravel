<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Channel;

use Illuminate\Contracts\Mail\Mailer;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Infrastructure\Alert\Mail\JobsMonitorAlertMail;

/**
 * Delivers an alert as an HTML email to the configured recipient list.
 *
 * Uses the host app's configured mailer. One Mailable per send() call,
 * with every recipient on the To: line (not BCC — these are ops alerts,
 * transparency is wanted).
 */
final class MailNotificationChannel implements NotificationChannel
{
    /**
     * @param  list<string>  $recipients
     */
    public function __construct(
        private readonly Mailer $mailer,
        private readonly array $recipients,
        private readonly ?string $sourceName = null,
        private readonly ?string $monitorBaseUrl = null,
    ) {}

    public function name(): string
    {
        return 'mail';
    }

    public function send(AlertPayload $payload): void
    {
        $this->assertHasRecipients();

        $this->mailer
            ->to($this->recipients)
            ->send(new JobsMonitorAlertMail(
                $payload,
                $this->sourceName,
                $this->monitorBaseUrl,
            ));
    }

    private function assertHasRecipients(): void
    {
        if ($this->recipients !== []) {
            return;
        }

        throw new RuntimeException('Mail alert channel has no mail recipients configured.');
    }
}
