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

final class DlqBulkControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bulk_retry_rejects_request_with_no_ids(): void
    {
        $response = $this->postJson('/jobs-monitor/dlq/bulk/retry', ['ids' => []]);

        $response->assertStatus(422);
    }

    public function test_bulk_retry_rejects_request_exceeding_max_ids(): void
    {
        $ids = [];
        for ($i = 1; $i <= 101; $i++) {
            $ids[] = sprintf('550e8400-e29b-41d4-a716-%012d', $i);
        }

        $response = $this->postJson('/jobs-monitor/dlq/bulk/retry', ['ids' => $ids]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids');
    }

    public function test_bulk_retry_rejects_non_uuid_ids(): void
    {
        $response = $this->postJson('/jobs-monitor/dlq/bulk/retry', [
            'ids' => ['not-a-uuid'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids.0');
    }

    public function test_bulk_retry_returns_counts_and_dispatches_each_job(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $repository = $this->app->make(JobRecordRepository::class);
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440001',
            '550e8400-e29b-41d4-a716-446655440002',
            '550e8400-e29b-41d4-a716-446655440003',
        ];
        foreach ($uuids as $uuid) {
            $this->storeRetryableJob($repository, $uuid);
        }

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushRaw')->times(3)->andReturn('ok');
        $factory = Mockery::mock(QueueFactory::class);
        $factory->shouldReceive('connection')->with('redis')->times(3)->andReturn($queue);
        $this->app->instance(QueueFactory::class, $factory);

        $response = $this->postJson('/jobs-monitor/dlq/bulk/retry', ['ids' => $uuids]);

        $response->assertOk();
        $response->assertJson([
            'succeeded' => 3,
            'failed' => 0,
            'total' => 3,
        ]);
        self::assertEmpty($response->json('errors'));
    }

    public function test_bulk_retry_records_missing_record_as_error_without_failing_batch(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $repository = $this->app->make(JobRecordRepository::class);
        $existing = '550e8400-e29b-41d4-a716-446655440001';
        $missing = '00000000-0000-0000-0000-000000000000';
        $this->storeRetryableJob($repository, $existing);

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushRaw')->once()->andReturn('ok');
        $factory = Mockery::mock(QueueFactory::class);
        $factory->shouldReceive('connection')->with('redis')->once()->andReturn($queue);
        $this->app->instance(QueueFactory::class, $factory);

        $response = $this->postJson('/jobs-monitor/dlq/bulk/retry', [
            'ids' => [$missing, $existing],
        ]);

        $response->assertOk();
        $response->assertJson([
            'succeeded' => 1,
            'failed' => 1,
            'total' => 2,
        ]);
        $response->assertJsonPath("errors.{$missing}", 'No record found for job '.$missing.'.');
    }

    public function test_bulk_retry_is_blocked_by_gate_when_denied(): void
    {
        $this->app['config']->set('jobs-monitor.dlq.authorization', 'manage-jobs-monitor');
        Gate::define('manage-jobs-monitor', static fn ($user, string $action) => false);

        $response = $this->actingAs($this->fakeUser())->postJson(
            '/jobs-monitor/dlq/bulk/retry',
            ['ids' => ['550e8400-e29b-41d4-a716-446655440001']],
        );

        $response->assertForbidden();
    }

    public function test_bulk_delete_removes_all_attempts_for_given_uuids(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440001',
            '550e8400-e29b-41d4-a716-446655440002',
        ];
        foreach ($uuids as $uuid) {
            $this->storeFailedJob($repository, $uuid);
        }

        $response = $this->postJson('/jobs-monitor/dlq/bulk/delete', ['ids' => $uuids]);

        $response->assertOk();
        $response->assertJson(['succeeded' => 2, 'failed' => 0, 'total' => 2]);
        foreach ($uuids as $uuid) {
            self::assertSame([], $repository->findAllAttempts(new JobIdentifier($uuid)));
        }
    }

    public function test_bulk_delete_records_missing_ids_as_errors(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $existing = '550e8400-e29b-41d4-a716-446655440001';
        $missing = '00000000-0000-0000-0000-000000000000';
        $this->storeFailedJob($repository, $existing);

        $response = $this->postJson('/jobs-monitor/dlq/bulk/delete', [
            'ids' => [$existing, $missing],
        ]);

        $response->assertOk();
        $response->assertJson(['succeeded' => 1, 'failed' => 1, 'total' => 2]);
        $response->assertJsonPath("errors.{$missing}", 'Dead-letter entry not found.');
    }

    public function test_bulk_delete_rejects_request_exceeding_max_ids(): void
    {
        $ids = [];
        for ($i = 1; $i <= 101; $i++) {
            $ids[] = sprintf('550e8400-e29b-41d4-a716-%012d', $i);
        }

        $response = $this->postJson('/jobs-monitor/dlq/bulk/delete', ['ids' => $ids]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids');
    }

    public function test_bulk_delete_is_blocked_by_gate_when_denied(): void
    {
        $this->app['config']->set('jobs-monitor.dlq.authorization', 'manage-jobs-monitor');
        Gate::define('manage-jobs-monitor', static fn ($user, string $action) => false);

        $response = $this->actingAs($this->fakeUser())->postJson(
            '/jobs-monitor/dlq/bulk/delete',
            ['ids' => ['550e8400-e29b-41d4-a716-446655440001']],
        );

        $response->assertForbidden();
    }

    private function storeRetryableJob(JobRecordRepository $repository, string $uuid): void
    {
        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->setPayload(['uuid' => $uuid, 'job' => 'App\\Jobs\\SendInvoice', 'data' => []]);
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $repository->save($record);
    }

    private function storeFailedJob(JobRecordRepository $repository, string $uuid): void
    {
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
