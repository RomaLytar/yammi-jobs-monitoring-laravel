<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\RecordWorkerHeartbeatAction;
use Yammi\JobsMonitor\Application\DTO\WorkerHeartbeatData;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Tests\Support\ArrayHeartbeatRateLimiter;
use Yammi\JobsMonitor\Tests\Support\InMemoryWorkerRepository;

final class RecordWorkerHeartbeatActionTest extends TestCase
{
    public function test_records_heartbeat_when_rate_limiter_allows(): void
    {
        $repo = new InMemoryWorkerRepository;
        $limiter = new ArrayHeartbeatRateLimiter;
        $action = new RecordWorkerHeartbeatAction($repo, $limiter, intervalSeconds: 30);

        $action($this->data('host-a:1234'));

        $worker = $repo->find(new WorkerIdentifier('host-a:1234'));
        self::assertNotNull($worker);
        self::assertSame('redis', $worker->heartbeat()->connection);
        self::assertSame('default', $worker->heartbeat()->queue);
        self::assertSame(1234, $worker->heartbeat()->pid);
    }

    public function test_second_call_within_interval_is_skipped(): void
    {
        $repo = new InMemoryWorkerRepository;
        $limiter = new ArrayHeartbeatRateLimiter;
        $action = new RecordWorkerHeartbeatAction($repo, $limiter, intervalSeconds: 30);

        $action($this->data('host-a:1234', observedAt: new DateTimeImmutable('2026-04-16 10:00:00')));
        $action($this->data('host-a:1234', queue: 'emails', observedAt: new DateTimeImmutable('2026-04-16 10:00:10')));

        $worker = $repo->find(new WorkerIdentifier('host-a:1234'));
        self::assertNotNull($worker);
        self::assertSame('default', $worker->heartbeat()->queue);
        self::assertSame(
            '2026-04-16 10:00:00',
            $worker->heartbeat()->lastSeenAt->format('Y-m-d H:i:s'),
        );
    }

    public function test_rate_limit_is_independent_per_worker(): void
    {
        $repo = new InMemoryWorkerRepository;
        $limiter = new ArrayHeartbeatRateLimiter;
        $action = new RecordWorkerHeartbeatAction($repo, $limiter, intervalSeconds: 30);

        $action($this->data('host-a:1111'));
        $action($this->data('host-b:2222'));

        self::assertSame(2, $repo->countAll());
    }

    public function test_reset_allows_write_to_propagate_updated_fields(): void
    {
        $repo = new InMemoryWorkerRepository;
        $limiter = new ArrayHeartbeatRateLimiter;
        $action = new RecordWorkerHeartbeatAction($repo, $limiter, intervalSeconds: 30);

        $action($this->data('host-a:1234', observedAt: new DateTimeImmutable('2026-04-16 10:00:00')));
        $limiter->reset();
        $action($this->data('host-a:1234', queue: 'emails', observedAt: new DateTimeImmutable('2026-04-16 10:01:00')));

        $worker = $repo->find(new WorkerIdentifier('host-a:1234'));
        self::assertNotNull($worker);
        self::assertSame('emails', $worker->heartbeat()->queue);
    }

    private function data(
        string $workerId,
        string $connection = 'redis',
        string $queue = 'default',
        string $host = 'host-a',
        int $pid = 1234,
        ?DateTimeImmutable $observedAt = null,
    ): WorkerHeartbeatData {
        return new WorkerHeartbeatData(
            workerId: $workerId,
            connection: $connection,
            queue: $queue,
            host: $host,
            pid: $pid,
            observedAt: $observedAt ?? new DateTimeImmutable('2026-04-16 10:00:00'),
        );
    }
}
