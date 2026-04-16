<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Persistence\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentWorkerRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class EloquentWorkerRepositoryTest extends TestCase
{
    private EloquentWorkerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new EloquentWorkerRepository;
    }

    public function test_record_heartbeat_upserts_on_worker_id(): void
    {
        $at1 = new DateTimeImmutable('2026-04-16 10:00:00');
        $at2 = new DateTimeImmutable('2026-04-16 10:02:00');

        $this->repo->recordHeartbeat($this->heartbeat('host-a:1234', lastSeenAt: $at1, queue: 'default'));
        $this->repo->recordHeartbeat($this->heartbeat('host-a:1234', lastSeenAt: $at2, queue: 'emails'));

        self::assertSame(1, $this->repo->countAll());

        $worker = $this->repo->find(new WorkerIdentifier('host-a:1234'));
        self::assertNotNull($worker);
        self::assertSame('emails', $worker->heartbeat()->queue);
        self::assertSame(
            $at2->getTimestamp(),
            $worker->heartbeat()->lastSeenAt->getTimestamp(),
        );
    }

    public function test_find_returns_null_when_worker_unknown(): void
    {
        self::assertNull($this->repo->find(new WorkerIdentifier('unknown:0')));
    }

    public function test_mark_stopped_sets_stopped_at(): void
    {
        $this->repo->recordHeartbeat($this->heartbeat('host-a:1234'));
        $this->repo->markStopped(
            new WorkerIdentifier('host-a:1234'),
            new DateTimeImmutable('2026-04-16 10:05:00'),
        );

        $worker = $this->repo->find(new WorkerIdentifier('host-a:1234'));

        self::assertNotNull($worker);
        self::assertNotNull($worker->stoppedAt());
        self::assertSame(
            '2026-04-16 10:05:00',
            $worker->stoppedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function test_record_heartbeat_clears_previous_stopped_at(): void
    {
        $this->repo->recordHeartbeat($this->heartbeat('host-a:1234'));
        $this->repo->markStopped(new WorkerIdentifier('host-a:1234'), new DateTimeImmutable('2026-04-16 10:05:00'));
        $this->repo->recordHeartbeat($this->heartbeat('host-a:1234', lastSeenAt: new DateTimeImmutable('2026-04-16 10:06:00')));

        $worker = $this->repo->find(new WorkerIdentifier('host-a:1234'));

        self::assertNotNull($worker);
        self::assertNull($worker->stoppedAt());
    }

    public function test_find_silent_since_returns_only_non_stopped_workers_before_cutoff(): void
    {
        $this->repo->recordHeartbeat($this->heartbeat('alive:1', lastSeenAt: new DateTimeImmutable('2026-04-16 10:04:00')));
        $this->repo->recordHeartbeat($this->heartbeat('silent:1', lastSeenAt: new DateTimeImmutable('2026-04-16 09:50:00')));
        $this->repo->recordHeartbeat($this->heartbeat('stopped:1', lastSeenAt: new DateTimeImmutable('2026-04-16 09:50:00')));
        $this->repo->markStopped(new WorkerIdentifier('stopped:1'), new DateTimeImmutable('2026-04-16 09:51:00'));

        $silent = $this->repo->findSilentSince(new DateTimeImmutable('2026-04-16 10:00:00'));

        self::assertCount(1, $silent);
        self::assertSame('silent:1', $silent[0]->heartbeat()->workerId->value);
    }

    public function test_count_alive_by_queue_key_groups_by_connection_and_queue(): void
    {
        $this->repo->recordHeartbeat($this->heartbeat('a:1', connection: 'redis', queue: 'default'));
        $this->repo->recordHeartbeat($this->heartbeat('a:2', connection: 'redis', queue: 'default'));
        $this->repo->recordHeartbeat($this->heartbeat('b:1', connection: 'redis', queue: 'emails'));
        $this->repo->recordHeartbeat($this->heartbeat('c:1', connection: 'sqs', queue: 'default'));
        $this->repo->markStopped(new WorkerIdentifier('a:2'), new DateTimeImmutable('2026-04-16 10:00:00'));

        $counts = $this->repo->countAliveByQueueKey(new DateTimeImmutable('2026-04-16 09:00:00'));

        self::assertSame(1, $counts['redis:default']);
        self::assertSame(1, $counts['redis:emails']);
        self::assertSame(1, $counts['sqs:default']);
    }

    public function test_delete_older_than_removes_only_stale_rows(): void
    {
        $this->repo->recordHeartbeat($this->heartbeat('stale:1', lastSeenAt: new DateTimeImmutable('2026-04-10 00:00:00')));
        $this->repo->recordHeartbeat($this->heartbeat('fresh:1', lastSeenAt: new DateTimeImmutable('2026-04-16 10:00:00')));

        $deleted = $this->repo->deleteOlderThan(new DateTimeImmutable('2026-04-15 00:00:00'));

        self::assertSame(1, $deleted);
        self::assertSame(1, $this->repo->countAll());
        self::assertNotNull($this->repo->find(new WorkerIdentifier('fresh:1')));
        self::assertNull($this->repo->find(new WorkerIdentifier('stale:1')));
    }

    public function test_find_paginated_orders_newest_heartbeat_first(): void
    {
        $this->repo->recordHeartbeat($this->heartbeat('a:1', lastSeenAt: new DateTimeImmutable('2026-04-16 09:00:00')));
        $this->repo->recordHeartbeat($this->heartbeat('b:1', lastSeenAt: new DateTimeImmutable('2026-04-16 10:00:00')));
        $this->repo->recordHeartbeat($this->heartbeat('c:1', lastSeenAt: new DateTimeImmutable('2026-04-16 08:00:00')));

        $page = $this->repo->findPaginated(perPage: 10, page: 1);

        self::assertSame(['b:1', 'a:1', 'c:1'], array_map(
            fn ($w) => $w->heartbeat()->workerId->value,
            $page,
        ));
    }

    private function heartbeat(
        string $workerId,
        string $connection = 'redis',
        string $queue = 'default',
        string $host = 'host-a',
        int $pid = 1234,
        ?DateTimeImmutable $lastSeenAt = null,
    ): WorkerHeartbeat {
        return new WorkerHeartbeat(
            workerId: new WorkerIdentifier($workerId),
            connection: $connection,
            queue: $queue,
            host: $host,
            pid: $pid,
            lastSeenAt: $lastSeenAt ?? new DateTimeImmutable('2026-04-16 10:00:00'),
        );
    }
}
