<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Worker\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Worker\Exception\InvalidWorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

final class WorkerHeartbeatTest extends TestCase
{
    public function test_constructs_with_all_fields(): void
    {
        $now = new DateTimeImmutable('2026-04-16T10:00:00+00:00');
        $hb = new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: 'redis',
            queue: 'default',
            host: 'host-a',
            pid: 1234,
            lastSeenAt: $now,
        );

        self::assertSame('host-a:1234', $hb->workerId->value);
        self::assertSame('redis', $hb->connection);
        self::assertSame('default', $hb->queue);
        self::assertSame('host-a', $hb->host);
        self::assertSame(1234, $hb->pid);
        self::assertSame($now, $hb->lastSeenAt);
    }

    public function test_rejects_blank_connection(): void
    {
        $this->expectException(InvalidWorkerHeartbeat::class);

        new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: '  ',
            queue: 'default',
            host: 'host-a',
            pid: 1234,
            lastSeenAt: new DateTimeImmutable,
        );
    }

    public function test_rejects_blank_queue(): void
    {
        $this->expectException(InvalidWorkerHeartbeat::class);

        new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: 'redis',
            queue: '',
            host: 'host-a',
            pid: 1234,
            lastSeenAt: new DateTimeImmutable,
        );
    }

    public function test_rejects_blank_host(): void
    {
        $this->expectException(InvalidWorkerHeartbeat::class);

        new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: 'redis',
            queue: 'default',
            host: '',
            pid: 1234,
            lastSeenAt: new DateTimeImmutable,
        );
    }

    public function test_rejects_non_positive_pid(): void
    {
        $this->expectException(InvalidWorkerHeartbeat::class);

        new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:0'),
            connection: 'redis',
            queue: 'default',
            host: 'host-a',
            pid: 0,
            lastSeenAt: new DateTimeImmutable,
        );
    }

    public function test_trims_text_fields(): void
    {
        $hb = new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: '  redis  ',
            queue: "\tdefault\n",
            host: ' host-a ',
            pid: 1234,
            lastSeenAt: new DateTimeImmutable,
        );

        self::assertSame('redis', $hb->connection);
        self::assertSame('default', $hb->queue);
        self::assertSame('host-a', $hb->host);
    }

    public function test_queue_key_returns_connection_and_queue_joined(): void
    {
        $hb = new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: 'redis',
            queue: 'emails',
            host: 'host-a',
            pid: 1234,
            lastSeenAt: new DateTimeImmutable,
        );

        self::assertSame('redis:emails', $hb->queueKey());
    }
}
