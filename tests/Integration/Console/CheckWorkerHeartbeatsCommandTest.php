<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Console;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertAction;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Tests\Support\RecordingNotificationChannel;
use Yammi\JobsMonitor\Tests\TestCase;

final class CheckWorkerHeartbeatsCommandTest extends TestCase
{
    private RecordingNotificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new RecordingNotificationChannel('slack');
        $this->app->instance(
            NotificationChannel::class.':slack',
            $this->channel,
        );
        // The SendAlertAction binding iterates registered channels, so we
        // short-circuit by rebinding it with our recorder.
        $this->app->bind(
            \Yammi\JobsMonitor\Application\Action\SendAlertAction::class,
            fn () => new \Yammi\JobsMonitor\Application\Action\SendAlertAction(
                [$this->channel],
                $this->app->make(\Psr\Log\LoggerInterface::class),
            ),
        );
    }

    public function test_command_emits_trigger_for_silent_worker_and_resolve_after_recovery(): void
    {
        $this->app['config']->set('jobs-monitor.workers.silent_after_seconds', 60);
        $this->app['config']->set('jobs-monitor.workers.channels', ['slack']);

        /** @var WorkerRepository $repo */
        $repo = $this->app->make(WorkerRepository::class);

        // 1. Worker silent → trigger alert
        $repo->recordHeartbeat(new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: 'redis',
            queue: 'default',
            host: 'host-a',
            pid: 1234,
            lastSeenAt: (new DateTimeImmutable)->modify('-10 minutes'),
        ));

        $this->artisan('jobs-monitor:heartbeats:check')
            ->assertExitCode(0);

        self::assertCount(1, $this->channel->sent);
        self::assertSame(AlertTrigger::WorkerSilent, $this->channel->sent[0]->trigger);
        self::assertSame(AlertAction::Trigger, $this->channel->sent[0]->action);

        // 2. Running the command again without recovery emits nothing new.
        $this->artisan('jobs-monitor:heartbeats:check')->assertExitCode(0);
        self::assertCount(1, $this->channel->sent);

        // 3. Heartbeat resumes → resolve.
        $repo->recordHeartbeat(new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1234'),
            connection: 'redis',
            queue: 'default',
            host: 'host-a',
            pid: 1234,
            lastSeenAt: new DateTimeImmutable,
        ));

        $this->artisan('jobs-monitor:heartbeats:check')->assertExitCode(0);

        self::assertCount(2, $this->channel->sent);
        self::assertSame(AlertAction::Resolve, $this->channel->sent[1]->action);
        self::assertSame('worker_silent:host-a:1234', $this->channel->sent[1]->fingerprint);
    }

    public function test_command_emits_underprovisioned_when_below_expected(): void
    {
        $this->app['config']->set('jobs-monitor.workers.silent_after_seconds', 60);
        $this->app['config']->set('jobs-monitor.workers.channels', ['slack']);
        $this->app['config']->set('jobs-monitor.workers.expected', [
            'redis:default' => 2,
        ]);

        /** @var WorkerRepository $repo */
        $repo = $this->app->make(WorkerRepository::class);

        $repo->recordHeartbeat(new WorkerHeartbeat(
            workerId: new WorkerIdentifier('host-a:1111'),
            connection: 'redis',
            queue: 'default',
            host: 'host-a',
            pid: 1111,
            lastSeenAt: new DateTimeImmutable,
        ));

        $this->artisan('jobs-monitor:heartbeats:check')->assertExitCode(0);

        self::assertCount(1, $this->channel->sent);
        self::assertSame(AlertTrigger::WorkerUnderprovisioned, $this->channel->sent[0]->trigger);
        self::assertSame(1, $this->channel->sent[0]->context['observed']);
        self::assertSame(2, $this->channel->sent[0]->context['expected']);
    }

    public function test_command_emits_nothing_with_empty_fleet(): void
    {
        $this->artisan('jobs-monitor:heartbeats:check')->assertExitCode(0);

        self::assertSame([], $this->channel->sent);
    }
}
