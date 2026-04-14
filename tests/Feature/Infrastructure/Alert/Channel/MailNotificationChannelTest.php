<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Alert\Channel;

use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\MailNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Mail\JobsMonitorAlertMail;
use Yammi\JobsMonitor\Tests\TestCase;

final class MailNotificationChannelTest extends TestCase
{
    public function test_name_is_mail(): void
    {
        $channel = new MailNotificationChannel($this->mailer(), ['ops@example.com']);

        self::assertSame('mail', $channel->name());
    }

    public function test_sends_mail_to_every_configured_recipient(): void
    {
        Mail::fake();

        $channel = new MailNotificationChannel(
            $this->mailer(),
            ['ops@example.com', 'oncall@example.com'],
        );

        $channel->send($this->samplePayload());

        Mail::assertSent(JobsMonitorAlertMail::class, 1);
        Mail::assertSent(
            JobsMonitorAlertMail::class,
            fn (JobsMonitorAlertMail $mail) => $mail->hasTo('ops@example.com')
                && $mail->hasTo('oncall@example.com'),
        );
    }

    public function test_subject_and_body_reflect_payload(): void
    {
        Mail::fake();

        $channel = new MailNotificationChannel($this->mailer(), ['ops@example.com']);

        $channel->send($this->samplePayload());

        Mail::assertSent(JobsMonitorAlertMail::class, function (JobsMonitorAlertMail $mail): bool {
            $mail->build();

            return $mail->subject === '[jobs-monitor] Failure rate spike'
                && $mail->viewData['body'] === '12 failures in the last 5m'
                && $mail->viewData['triggerLabel'] === 'Failure rate';
        });
    }

    public function test_empty_recipient_list_raises_exception(): void
    {
        $channel = new MailNotificationChannel($this->mailer(), []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no mail recipients');

        $channel->send($this->samplePayload());
    }

    private function samplePayload(): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::FailureRate,
            subject: 'Failure rate spike',
            body: '12 failures in the last 5m',
            context: ['count' => 12],
            triggeredAt: new DateTimeImmutable('2026-04-13T12:00:00Z'),
        );
    }

    private function mailer(): Mailer
    {
        return $this->app->make(Mailer::class);
    }
}
