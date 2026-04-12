<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\SendAlertAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Tests\Support\Alert\FailingChannel;
use Yammi\JobsMonitor\Tests\Support\Alert\NullLogger;
use Yammi\JobsMonitor\Tests\Support\Alert\RecordingChannel;
use Yammi\JobsMonitor\Tests\Support\Alert\RecordingLogger;

final class SendAlertActionTest extends TestCase
{
    public function test_routes_payload_to_named_channels_only(): void
    {
        $slack = new RecordingChannel('slack');
        $mail = new RecordingChannel('mail');
        $pager = new RecordingChannel('pager');

        $action = new SendAlertAction([$slack, $mail, $pager], new NullLogger);
        $payload = $this->payload();

        $action($payload, ['slack', 'pager']);

        self::assertCount(1, $slack->sent);
        self::assertCount(0, $mail->sent);
        self::assertCount(1, $pager->sent);
        self::assertSame($payload, $slack->sent[0]);
    }

    public function test_unknown_channel_is_logged_and_skipped(): void
    {
        $slack = new RecordingChannel('slack');
        $logger = new RecordingLogger;

        $action = new SendAlertAction([$slack], $logger);

        $action($this->payload(), ['slack', 'nonexistent']);

        self::assertCount(1, $slack->sent);
        self::assertCount(1, $logger->records);
        self::assertStringContainsString('nonexistent', $logger->records[0]['message']);
    }

    public function test_failing_channel_does_not_prevent_other_channels(): void
    {
        $flaky = new FailingChannel('slack');
        $mail = new RecordingChannel('mail');
        $logger = new RecordingLogger;

        $action = new SendAlertAction([$flaky, $mail], $logger);

        $action($this->payload(), ['slack', 'mail']);

        self::assertCount(1, $mail->sent);
        self::assertCount(1, $logger->records);
        self::assertStringContainsString('slack', $logger->records[0]['message']);
    }

    public function test_zero_channels_is_a_no_op(): void
    {
        $slack = new RecordingChannel('slack');

        $action = new SendAlertAction([$slack], new NullLogger);
        $action($this->payload(), []);

        self::assertCount(0, $slack->sent);
    }

    private function payload(): AlertPayload
    {
        return new AlertPayload(
            trigger: AlertTrigger::FailureRate,
            subject: 's',
            body: 'b',
            context: [],
            triggeredAt: new DateTimeImmutable('2026-04-13T12:00:00Z'),
        );
    }
}
