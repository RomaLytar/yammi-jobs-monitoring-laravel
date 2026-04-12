<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\TestCase;

final class DlqControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dlq_page_returns_ok_empty_state(): void
    {
        $response = $this->get('/jobs-monitor/dlq');

        $response->assertOk();
        $response->assertSee('No dead-letter jobs');
    }

    public function test_dlq_page_lists_permanent_failures(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'validation', FailureCategory::Permanent);
        $repository->save($record);

        $response = $this->get('/jobs-monitor/dlq');

        $response->assertOk();
        $response->assertSee('SendInvoice');
        $response->assertSee('emails');
        $response->assertSee('Permanent');
    }

    public function test_dlq_shows_warning_when_retry_disabled(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', false);

        $response = $this->get('/jobs-monitor/dlq');

        $response->assertOk();
        $response->assertSee('Retry is disabled because payloads are not stored.');
    }

    public function test_delete_removes_all_attempts_for_the_uuid(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $repository->save($record);

        $response = $this->post("/jobs-monitor/dlq/{$uuid}/delete");

        $response->assertRedirect(route('jobs-monitor.dlq'));
        self::assertSame([], $repository->findAllAttempts(new JobIdentifier($uuid)));
    }

    public function test_retry_pushes_raw_payload_on_the_host_queue(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $record->setPayload(['foo' => 'bar']);
        $repository->save($record);

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushRaw')
            ->once()
            ->with(Mockery::on(fn ($raw) => str_contains($raw, '"foo":"bar"')), 'emails');

        $factory = Mockery::mock(QueueFactory::class);
        $factory->shouldReceive('connection')->with('redis')->once()->andReturn($queue);
        $this->app->instance(QueueFactory::class, $factory);

        $response = $this->post("/jobs-monitor/dlq/{$uuid}/retry");

        $response->assertRedirect(route('jobs-monitor.dlq'));
        $response->assertSessionHas('status');
    }

    public function test_retry_shows_error_when_payload_missing(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $repository->save($record);

        $response = $this->post("/jobs-monitor/dlq/{$uuid}/retry");

        $response->assertRedirect(route('jobs-monitor.dlq'));
        $response->assertSessionHas('error');
    }

    public function test_authorization_gate_blocks_destructive_action_when_denied(): void
    {
        $this->app['config']->set('jobs-monitor.dlq.authorization', 'manage-jobs-monitor');

        Gate::define('manage-jobs-monitor', static fn ($user, string $action) => false);

        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $response = $this->actingAs($this->fakeUser())->post("/jobs-monitor/dlq/{$uuid}/delete");

        $response->assertForbidden();
    }

    public function test_authorization_gate_allows_destructive_action_when_granted(): void
    {
        $this->app['config']->set('jobs-monitor.dlq.authorization', 'manage-jobs-monitor');

        Gate::define('manage-jobs-monitor', static fn ($user, string $action) => true);

        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $response = $this->actingAs($this->fakeUser())->post("/jobs-monitor/dlq/{$uuid}/delete");

        $response->assertRedirect(route('jobs-monitor.dlq'));
    }

    private function fakeUser(): Authenticatable
    {
        return new class implements Authenticatable
        {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }
}
