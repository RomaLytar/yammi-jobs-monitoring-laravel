<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\MarkWorkerStoppedAction;
use Yammi\JobsMonitor\Application\Action\RecordWorkerHeartbeatAction;
use Yammi\JobsMonitor\Application\DTO\WorkerHeartbeatData;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Tests\Support\ArrayHeartbeatRateLimiter;
use Yammi\JobsMonitor\Tests\Support\InMemoryWorkerRepository;

final class MarkWorkerStoppedActionTest extends TestCase
{
    public function test_sets_stopped_at_on_existing_worker(): void
    {
        $repo = new InMemoryWorkerRepository;
        (new RecordWorkerHeartbeatAction($repo, new ArrayHeartbeatRateLimiter, 30))(
            new WorkerHeartbeatData(
                workerId: 'host-a:1234',
                connection: 'redis',
                queue: 'default',
                host: 'host-a',
                pid: 1234,
                observedAt: new DateTimeImmutable('2026-04-16 10:00:00'),
            ),
        );

        (new MarkWorkerStoppedAction($repo))(
            new WorkerIdentifier('host-a:1234'),
            new DateTimeImmutable('2026-04-16 10:05:00'),
        );

        $worker = $repo->find(new WorkerIdentifier('host-a:1234'));
        self::assertNotNull($worker);
        self::assertNotNull($worker->stoppedAt());
        self::assertSame(
            '2026-04-16 10:05:00',
            $worker->stoppedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function test_is_noop_for_unknown_worker(): void
    {
        $repo = new InMemoryWorkerRepository;

        (new MarkWorkerStoppedAction($repo))(
            new WorkerIdentifier('ghost:0000'),
            new DateTimeImmutable('2026-04-16 10:05:00'),
        );

        self::assertSame(0, $repo->countAll());
        self::assertNull($repo->find(new WorkerIdentifier('ghost:0000')));
    }
}
