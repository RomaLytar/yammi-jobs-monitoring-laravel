<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

/**
 * @internal
 */
final class JobsMonitorAlertMail extends Mailable
{
    use SerializesModels;

    public function __construct(private readonly AlertPayload $payload) {}

    public function build(): self
    {
        return $this
            ->subject('[jobs-monitor] '.$this->payload->subject)
            ->view('jobs-monitor::mail.alert', [
                'subject' => $this->payload->subject,
                'body' => $this->payload->body,
                'triggerLabel' => $this->payload->trigger->label(),
                'triggeredAt' => $this->payload->triggeredAt,
                'context' => $this->payload->context,
            ]);
    }
}
