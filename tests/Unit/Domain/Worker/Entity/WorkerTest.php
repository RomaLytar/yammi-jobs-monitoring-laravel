<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Worker\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\Enum\WorkerStatus;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

final class WorkerTest extends TestCase
{
    public function test_worker_stopping_is_always_dead_regardless_of_threshold(): void
    {
        $now = new DateTimeImmutable('2026-04-16T10:00:00+00:00');
        $worker = new Worker(
            heartbeat: $this->heartbeatAt($now),
            stoppedAt: $now,
        );

        self::assertSame(WorkerStatus::Dead, $worker->classifyStatus($now, silentAfterSeconds: 120));
    }

    public function test_recent_heartbeat_within_threshold_is_alive(): void
    {
        $seen = new DateTimeImmutable('2026-04-16T10:00:00+00:00');
        $now = new DateTimeImmutable('2026-04-16T10:01:30+00:00'); // +90s

        $worker = new Worker(heartbeat: $this->heartbeatAt($seen));

        self::assertSame(WorkerStatus::Alive, $worker->classifyStatus($now, silentAfterSeconds: 120));
    }

    public function test_heartbeat_older_than_threshold_is_silent(): void
    {
        $seen = new DateTimeImmutable('2026-04-16T10:00:00+00:00');
        $now = new DateTimeImmutable('2026-04-16T10:03:00+00:00'); // +180s

        $worker = new Worker(heartbeat: $this->heartbeatAt($seen));

        self::assertSame(WorkerStatus::Silent, $worker->classifyStatus($now, silentAfterSeconds: 120));
    }

    public function test_heartbeat_older_than_dead_multiplier_is_dead(): void
    {
        $seen = new DateTimeImmutable('2026-04-16T10:00:00+00:00');
        // 10 * silent threshold = dead
        $now = new DateTimeImmutable('2026-04-16T10:20:01+00:00'); // +1201s

        $worker = new Worker(heartbeat: $this->heartbeatAt($seen));

        self::assertSame(WorkerStatus::Dead, $worker->classifyStatus($now, silentAfterSeconds: 120));
    }

    public function test_boundary_at_threshold_is_still_alive(): void
    {
        $seen = new DateTimeImmutable('2026-04-16T10:00:00+00:00');
        $now = new DateTimeImmutable('2026-04-16T10:02:00+00:00'); // exactly +120s

        $worker = new Worker(heartbeat: $this->heartbeatAt($seen));

        self::assertSame(WorkerStatus::Alive, $worker->classifyStatus($now, silentAfterSeconds: 120));
    }

    public function test_heartbeat_in_the_future_is_alive(): void
    {
        $seen = new DateTimeImmutable('2026-04-16T10:05:00+00:00');
        $now = new DateTimeImmutable('2026-04-16T10:00:00+00:00');

        $worker = new Worker(heartbeat: $this->heartbeatAt($seen));

        self::assertSame(WorkerStatus::Alive, $worker->classifyStatus($now, silentAfterSeconds: 120));
    }

    public function test_exposes_heartbeat_fields(): void
    {
        $seen = new DateTimeImmutable('2026-04-16T10:00:00+00:00');
        $heartbeat = $this->heartbeatAt($seen);

        $worker = new Worker(heartbeat: $heartbeat);

        self::assertSame($heartbeat, $worker->heartbeat());
        self::assertSame('host-a:1234', $worker->heartbeat()->workerId->value);
        self::assertNull($worker->stoppedAt());
    }

    private function heartbeatAt(DateTimeImmutable $seen): WorkerHeartbeat
    {
        return new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: 'redis',
            queue: 'default',
            host: 'host-a',
            pid: 1234,
            lastSeenAt: $seen,
        );
    }
}
