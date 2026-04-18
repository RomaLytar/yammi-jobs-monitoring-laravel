<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\FailureSample;

/**
 * @internal
 */
final class JobsMonitorAlertMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        private readonly AlertPayload $payload,
        private readonly ?string $sourceName = null,
        private readonly ?string $monitorBaseUrl = null,
    ) {}

    public function build(): self
    {
        $subjectPrefix = $this->sourceName !== null && $this->sourceName !== ''
            ? sprintf('[%s] ', $this->sourceName)
            : '[jobs-monitor] ';

<<<<<<< HEAD
        if ($this->payload->action->isResolve()) {
            $subjectPrefix .= '[Resolved] ';
        }

=======
>>>>>>> origin/main
        return $this
            ->subject($subjectPrefix.$this->payload->subject)
            ->view('jobs-monitor::mail.alert', [
                'subject' => $this->payload->subject,
                'body' => $this->payload->body,
                'triggerLabel' => $this->payload->trigger->label(),
                'triggeredAt' => $this->payload->triggeredAt,
                'context' => $this->payload->context,
                'recentFailures' => $this->payload->recentFailures,
                'sourceName' => $this->sourceName,
                'dashboardUrl' => $this->monitorBaseUrl,
<<<<<<< HEAD
                'isResolve' => $this->payload->action->isResolve(),
=======
>>>>>>> origin/main
                'detailUrlBuilder' => fn (FailureSample $s): ?string => $this->monitorBaseUrl === null
                    ? null
                    : sprintf('%s/%s/%d', rtrim($this->monitorBaseUrl, '/'), $s->uuid, $s->attempt),
            ]);
    }
}
