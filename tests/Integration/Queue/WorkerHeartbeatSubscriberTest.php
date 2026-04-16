<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Queue;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Application\Contract\HeartbeatRateLimiter;
use Yammi\JobsMonitor\Application\Contract\WorkerIdentityResolver;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Tests\Support\ArrayHeartbeatRateLimiter;
use Yammi\JobsMonitor\Tests\TestCase;

final class WorkerHeartbeatSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WorkerIdentityResolver::class, new class implements WorkerIdentityResolver
        {
            public function resolve(): array
            {
                return ['host' => 'host-test', 'pid' => 4242];
            }
        });

        $this->app->instance(HeartbeatRateLimiter::class, new ArrayHeartbeatRateLimiter);
    }

    public function test_job_processing_event_records_heartbeat_row(): void
    {
        $this->app->make(Dispatcher::class)->dispatch(
            new JobProcessing('redis', $this->fakeJob(queue: 'emails')),
        );

        $row = DB::table('jobs_monitor_worker_heartbeats')->first();

        self::assertNotNull($row);
        self::assertSame('host-test:4242', $row->worker_id);
        self::assertSame('redis', $row->connection);
        self::assertSame('emails', $row->queue);
        self::assertSame('host-test', $row->host);
        self::assertSame(4242, (int) $row->pid);
        self::assertNull($row->stopped_at);
    }

    public function test_looping_event_records_heartbeat_row(): void
    {
        $this->app->make(Dispatcher::class)->dispatch(new Looping('redis', 'default'));

        $row = DB::table('jobs_monitor_worker_heartbeats')->first();

        self::assertNotNull($row);
        self::assertSame('host-test:4242', $row->worker_id);
        self::assertSame('default', $row->queue);
    }

    public function test_worker_stopping_event_sets_stopped_at(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->dispatch(new Looping('redis', 'default'));
        $dispatcher->dispatch(new WorkerStopping);

        $row = DB::table('jobs_monitor_worker_heartbeats')->first();

        self::assertNotNull($row);
        self::assertNotNull($row->stopped_at);
    }

    public function test_worker_stopping_is_noop_when_worker_never_seen(): void
    {
        $this->app->make(Dispatcher::class)->dispatch(new WorkerStopping);

        self::assertSame(0, DB::table('jobs_monitor_worker_heartbeats')->count());
    }

    public function test_failure_inside_listener_does_not_escape_to_the_host(): void
    {
        $this->app->instance(WorkerIdentityResolver::class, new class implements WorkerIdentityResolver
        {
            public function resolve(): array
            {
                throw new \RuntimeException('identity lookup exploded');
            }
        });

        $this->app->make(Dispatcher::class)->dispatch(new Looping('redis', 'default'));

        self::assertSame(0, DB::table('jobs_monitor_worker_heartbeats')->count());
    }

    public function test_rate_limiter_skips_second_write_for_the_same_worker(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->dispatch(new Looping('redis', 'default'));
        $dispatcher->dispatch(new Looping('redis', 'emails'));

        self::assertSame(1, DB::table('jobs_monitor_worker_heartbeats')->count());
        $row = DB::table('jobs_monitor_worker_heartbeats')->first();
        self::assertSame('default', $row->queue);
    }

    public function test_worker_stopping_bypasses_rate_limit(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->dispatch(new Looping('redis', 'default'));
        $dispatcher->dispatch(new WorkerStopping);

        $row = DB::table('jobs_monitor_worker_heartbeats')
            ->where('worker_id', (new WorkerIdentifier('host-test:4242'))->value)
            ->first();

        self::assertNotNull($row);
        self::assertNotNull($row->stopped_at);
    }

    private function fakeJob(string $queue = 'default'): JobContract
    {
        return new class($queue) implements JobContract
        {
            public function __construct(private readonly string $queueName) {}

            public function getQueue(): string
            {
                return $this->queueName;
            }

            // phpcs:disable -- contract boilerplate
            public function uuid(): ?string
            {
                return '00000000-0000-4000-8000-000000000000';
            }

            public function attempts(): int
            {
                return 1;
            }

            public function resolveName(): string
            {
                return 'App\\Jobs\\Fake';
            }

            public function getJobId(): ?string
            {
                return 'job-1';
            }

            public function getRawBody(): string
            {
                return '{}';
            }

            public function fire(): void {}

            public function release($delay = 0): void {}

            public function isReleased(): bool
            {
                return false;
            }

            public function isDeleted(): bool
            {
                return false;
            }

            public function isDeletedOrReleased(): bool
            {
                return false;
            }

            public function isReserved(): bool
            {
                return false;
            }

            public function hasFailed(): bool
            {
                return false;
            }

            public function markAsFailed(): void {}

            public function fail($e = null): void {}

            public function delete(): void {}

            public function getConnectionName(): string
            {
                return 'redis';
            }

            public function setConnectionName($name): void {}

            public function getContainer()
            {
                return null;
            }

            public function setContainer($container): void {}

            public function maxTries(): ?int
            {
                return null;
            }

            public function maxExceptions(): ?int
            {
                return null;
            }

            public function backoff(): null|int|array
            {
                return null;
            }

            public function retryUntil(): ?\DateTimeInterface
            {
                return null;
            }

            public function timeout(): ?int
            {
                return null;
            }

            public function payload(): array
            {
                return [];
            }

            public function getName(): string
            {
                return 'App\\Jobs\\Fake';
            }

            public function getJobClass(): string
            {
                return 'App\\Jobs\\Fake';
            }
            // phpcs:enable
        };
    }
}
