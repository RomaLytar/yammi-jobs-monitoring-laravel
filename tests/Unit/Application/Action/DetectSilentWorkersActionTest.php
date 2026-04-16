<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yammi\JobsMonitor\Application\Action\DetectSilentWorkersAction;
use Yammi\JobsMonitor\Application\Action\SendAlertAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Tests\Support\InMemoryWorkerAlertStateStore;
use Yammi\JobsMonitor\Tests\Support\InMemoryWorkerRepository;
use Yammi\JobsMonitor\Tests\Support\RecordingNotificationChannel;

final class DetectSilentWorkersActionTest extends TestCase
{
    public function test_no_workers_no_alerts(): void
    {
        [, $channel, $summary] = $this->runAction(workers: [], expected: []);

        self::assertSame(0, $summary->total());
        self::assertSame([], $channel->sent);
    }

    public function test_emits_trigger_for_newly_silent_worker(): void
    {
        [, $channel, $summary] = $this->runAction(
            workers: [
                'a:1' => ['lastSeenAt' => '2026-04-16 09:57:00', 'connection' => 'redis', 'queue' => 'default'],
            ],
            expected: [],
            now: new DateTimeImmutable('2026-04-16 10:00:00'),
            silentAfterSeconds: 60,
        );

        self::assertSame(1, $summary->silentTriggered);
        self::assertSame(0, $summary->silentResolved);
        self::assertCount(1, $channel->sent);
        self::assertSame(AlertTrigger::WorkerSilent, $channel->sent[0]->trigger);
        self::assertSame(AlertAction::Trigger, $channel->sent[0]->action);
        self::assertSame('worker_silent:a:1', $channel->sent[0]->fingerprint);
    }

    public function test_does_not_reemit_trigger_when_worker_already_silent(): void
    {
        $store = new InMemoryWorkerAlertStateStore;
        $store->replace('silent', ['a:1']);

        [, $channel, $summary] = $this->runAction(
            workers: ['a:1' => ['lastSeenAt' => '2026-04-16 09:57:00']],
            expected: [],
            now: new DateTimeImmutable('2026-04-16 10:00:00'),
            silentAfterSeconds: 60,
            store: $store,
        );

        self::assertSame(0, $summary->silentTriggered);
        self::assertSame([], $channel->sent);
    }

    public function test_emits_resolve_when_previously_silent_worker_recovers(): void
    {
        $store = new InMemoryWorkerAlertStateStore;
        $store->replace('silent', ['a:1']);

        [, $channel, $summary] = $this->runAction(
            workers: ['a:1' => ['lastSeenAt' => '2026-04-16 09:59:45']],
            expected: [],
            now: new DateTimeImmutable('2026-04-16 10:00:00'),
            silentAfterSeconds: 60,
            store: $store,
        );

        self::assertSame(1, $summary->silentResolved);
        self::assertCount(1, $channel->sent);
        self::assertSame(AlertAction::Resolve, $channel->sent[0]->action);
        self::assertSame(AlertTrigger::WorkerSilent, $channel->sent[0]->trigger);
    }

    public function test_emits_underprovisioned_trigger_when_below_expected(): void
    {
        [, $channel, $summary] = $this->runAction(
            workers: [
                'a:1' => ['connection' => 'redis', 'queue' => 'default', 'lastSeenAt' => '2026-04-16 09:59:50'],
            ],
            expected: ['redis:default' => 2],
            now: new DateTimeImmutable('2026-04-16 10:00:00'),
        );

        self::assertSame(1, $summary->underprovisionedTriggered);
        self::assertCount(1, $channel->sent);
        self::assertSame(AlertTrigger::WorkerUnderprovisioned, $channel->sent[0]->trigger);
        self::assertSame(1, $channel->sent[0]->context['observed']);
        self::assertSame(2, $channel->sent[0]->context['expected']);
    }

    public function test_emits_underprovisioned_resolve_when_queue_returns_to_strength(): void
    {
        $store = new InMemoryWorkerAlertStateStore;
        $store->replace('underprovisioned', ['redis:default']);

        [, $channel, $summary] = $this->runAction(
            workers: [
                'a:1' => ['connection' => 'redis', 'queue' => 'default', 'lastSeenAt' => '2026-04-16 09:59:50'],
                'a:2' => ['connection' => 'redis', 'queue' => 'default', 'lastSeenAt' => '2026-04-16 09:59:50'],
            ],
            expected: ['redis:default' => 2],
            now: new DateTimeImmutable('2026-04-16 10:00:00'),
            store: $store,
        );

        self::assertSame(1, $summary->underprovisionedResolved);
        self::assertCount(1, $channel->sent);
        self::assertSame(AlertAction::Resolve, $channel->sent[0]->action);
    }

    public function test_skips_underprovisioned_entirely_when_no_expectations_configured(): void
    {
        $store = new InMemoryWorkerAlertStateStore;
        $store->replace('underprovisioned', ['old:queue']);

        [, $channel, $summary] = $this->runAction(
            workers: ['a:1' => []],
            expected: [],
            store: $store,
        );

        self::assertSame(0, $summary->underprovisionedTriggered);
        self::assertSame(0, $summary->underprovisionedResolved);
        self::assertSame([], $store->active('underprovisioned'));
        self::assertSame([], $channel->sent);
    }

    public function test_stopped_workers_are_not_counted_as_silent(): void
    {
        $repo = new InMemoryWorkerRepository;
        $this->seedWorker($repo, 'stopped:1', lastSeenAt: '2026-04-16 09:57:00');
        $repo->markStopped(new WorkerIdentifier('stopped:1'), new DateTimeImmutable('2026-04-16 09:58:00'));

        $channel = new RecordingNotificationChannel;
        $action = new DetectSilentWorkersAction(
            repository: $repo,
            stateStore: new InMemoryWorkerAlertStateStore,
            sender: new SendAlertAction([$channel], new NullLogger),
            silentAfterSeconds: 60,
            expected: [],
            channels: ['slack'],
        );

        $summary = $action(new DateTimeImmutable('2026-04-16 10:00:00'));

        self::assertSame(0, $summary->silentTriggered);
        self::assertSame([], $channel->sent);
    }

    /**
     * @param  array<string, array{connection?: string, queue?: string, host?: string, pid?: int, lastSeenAt?: string}>  $workers
     * @param  array<string, int>  $expected
     * @return array{0: DetectSilentWorkersAction, 1: RecordingNotificationChannel, 2: \Yammi\JobsMonitor\Application\DTO\WorkerAlertSummary}
     */
    private function runAction(
        array $workers,
        array $expected = [],
        ?DateTimeImmutable $now = null,
        int $silentAfterSeconds = 60,
        ?InMemoryWorkerAlertStateStore $store = null,
    ): array {
        $repo = new InMemoryWorkerRepository;
        foreach ($workers as $id => $fields) {
            $this->seedWorker($repo, $id, ...$fields);
        }

        $channel = new RecordingNotificationChannel;
        $action = new DetectSilentWorkersAction(
            repository: $repo,
            stateStore: $store ?? new InMemoryWorkerAlertStateStore,
            sender: new SendAlertAction([$channel], new NullLogger),
            silentAfterSeconds: $silentAfterSeconds,
            expected: $expected,
            channels: ['slack'],
        );

        return [$action, $channel, $action($now ?? new DateTimeImmutable('2026-04-16 10:00:00'))];
    }

    private function seedWorker(
        InMemoryWorkerRepository $repo,
        string $workerId,
        string $connection = 'redis',
        string $queue = 'default',
        string $host = 'host-a',
        int $pid = 1234,
        string $lastSeenAt = '2026-04-16 10:00:00',
    ): void {
        $repo->recordHeartbeat(new WorkerHeartbeat(
            workerId: new WorkerIdentifier($workerId),
            connection: $connection,
            queue: $queue,
            host: $host,
            pid: $pid,
            lastSeenAt: new DateTimeImmutable($lastSeenAt),
        ));
    }
}
