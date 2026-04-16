<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Foundation\Application;
use Mockery;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\TestCase;

final class ApiBulkTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  Application  $app
     */
    protected function enableApi($app): void
    {
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    /**
     * @define-env enableApi
     */
    public function test_api_dlq_bulk_candidates_returns_every_dead_letter_uuid(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $uuid = sprintf('550e8400-e29b-41d4-a716-%012d', $i);
            $this->storeDeadLetter($repository, $uuid);
            $ids[] = $uuid;
        }

        $response = $this->getJson('/api/jobs-monitor/dlq/bulk/candidates');

        $response->assertOk();
        $response->assertJsonStructure(['ids', 'total', 'truncated']);
        self::assertEqualsCanonicalizing($ids, $response->json('ids'));
        self::assertSame(3, $response->json('total'));
    }

    /**
     * @define-env enableApi
     */
    public function test_api_failures_bulk_candidates_honors_queue_filter(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $emailsUuid = '550e8400-e29b-41d4-a716-446655440051';
        $otherUuid = '550e8400-e29b-41d4-a716-446655440052';
        $this->storeFailure($repository, $emailsUuid, 'emails');
        $this->storeFailure($repository, $otherUuid, 'other');

        $response = $this->getJson('/api/jobs-monitor/failures/bulk/candidates?queue=emails&period=all');

        $response->assertOk();
        self::assertSame([$emailsUuid], $response->json('ids'));
    }

    /**
     * @define-env enableApi
     */
    public function test_api_dlq_bulk_retry_returns_counts(): void
    {
        $this->authenticateUser();
        $this->app['config']->set('jobs-monitor.store_payload', true);
        $repository = $this->app->make(JobRecordRepository::class);
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440061',
            '550e8400-e29b-41d4-a716-446655440062',
        ];
        foreach ($uuids as $uuid) {
            $this->storeRetryableJob($repository, $uuid);
        }

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushRaw')->times(2)->andReturn('ok');
        $factory = Mockery::mock(QueueFactory::class);
        $factory->shouldReceive('connection')->with('redis')->times(2)->andReturn($queue);
        $this->app->instance(QueueFactory::class, $factory);

        $response = $this->postJson('/api/jobs-monitor/dlq/bulk/retry', ['ids' => $uuids]);

        $response->assertOk();
        $response->assertJson(['succeeded' => 2, 'failed' => 0, 'total' => 2]);
    }

    /**
     * @define-env enableApi
     */
    public function test_api_dlq_bulk_delete_removes_all_attempts(): void
    {
        $this->authenticateUser();
        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440071';
        $this->storeDeadLetter($repository, $uuid);

        $response = $this->postJson('/api/jobs-monitor/dlq/bulk/delete', ['ids' => [$uuid]]);

        $response->assertOk();
        $response->assertJson(['succeeded' => 1, 'failed' => 0, 'total' => 1]);
        self::assertSame([], $repository->findAllAttempts(new JobIdentifier($uuid)));
    }

    /**
     * @define-env enableApi
     */
    public function test_api_dlq_bulk_retry_rejects_exceeding_max_ids(): void
    {
        $ids = [];
        for ($i = 1; $i <= 101; $i++) {
            $ids[] = sprintf('550e8400-e29b-41d4-a716-%012d', $i);
        }

        $response = $this->postJson('/api/jobs-monitor/dlq/bulk/retry', ['ids' => $ids]);

        $response->assertStatus(422);
    }

    private function storeDeadLetter(JobRecordRepository $repository, string $uuid): void
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

    private function storeFailure(JobRecordRepository $repository, string $uuid, string $queue): void
    {
        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\Whatever',
            connection: 'redis',
            queue: new QueueName($queue),
            startedAt: new DateTimeImmutable,
        );
        $record->markAsFailed(new DateTimeImmutable, 'boom');
        $repository->save($record);
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
}
