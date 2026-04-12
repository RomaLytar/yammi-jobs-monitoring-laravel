<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Alert\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

final class AlertPayloadTest extends TestCase
{
    public function test_carries_subject_body_and_context(): void
    {
        $triggeredAt = new DateTimeImmutable('2026-04-13T10:00:00Z');

        $payload = new AlertPayload(
            trigger: AlertTrigger::FailureRate,
            subject: 'Failure rate spike',
            body: '12 failures in the last 5 minutes',
            context: ['count' => 12, 'window' => '5m'],
            triggeredAt: $triggeredAt,
        );

        self::assertSame(AlertTrigger::FailureRate, $payload->trigger);
        self::assertSame('Failure rate spike', $payload->subject);
        self::assertSame('12 failures in the last 5 minutes', $payload->body);
        self::assertSame(['count' => 12, 'window' => '5m'], $payload->context);
        self::assertSame($triggeredAt, $payload->triggeredAt);
    }

    public function test_context_defaults_to_empty_array(): void
    {
        $payload = new AlertPayload(
            trigger: AlertTrigger::DlqSize,
            subject: 'DLQ is growing',
            body: '',
            context: [],
            triggeredAt: new DateTimeImmutable,
        );

        self::assertSame([], $payload->context);
    }
}
