<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Mockery;
use Yammi\JobsMonitor\Application\Action\RecordFailureFingerprintAction;
use Yammi\JobsMonitor\Application\Action\StoreJobRecordAction;
use Yammi\JobsMonitor\Application\DTO\JobRecordData;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Tests\TestCase;

final class FailureGroupsApiControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_groups_ordered_by_last_seen(): void
    {
        $this->seedFailureGroupForUuid('550e8400-e29b-41d4-a716-446655440001', 'App\\Jobs\\A', new \RuntimeException('First'));
        $this->seedFailureGroupForUuid('550e8400-e29b-41d4-a716-446655440002', 'App\\Jobs\\B', new \RuntimeException('Second'));

        $response = $this->getJson('/jobs-monitor/failures/groups');

        $response->assertStatus(200);
        $data = $response->json('data');
        self::assertCount(2, $data);
        self::assertArrayHasKey('fingerprint', $data[0]);
        self::assertArrayHasKey('occurrences', $data[0]);
        self::assertArrayHasKey('affected_job_classes', $data[0]);
        self::assertArrayHasKey('first_seen_at', $data[0]);
        self::assertArrayHasKey('last_seen_at', $data[0]);
        self::assertSame(2, $response->json('meta.total'));
    }

    public function test_show_returns_group_details_and_related_job_uuids(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $fingerprint = $this->seedFailureGroupForUuid($uuid, 'App\\Jobs\\A', new \RuntimeException('Boom'));

        $response = $this->getJson("/jobs-monitor/failures/groups/{$fingerprint}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'fingerprint' => $fingerprint,
                'sample_exception_class' => 'RuntimeException',
                'sample_message' => 'Boom',
            ],
        ]);
        self::assertContains($uuid, $response->json('data.job_uuids'));
    }

    public function test_show_returns_404_for_unknown_fingerprint(): void
    {
        $response = $this->getJson('/jobs-monitor/failures/groups/0000000000000000');

        $response->assertStatus(404);
    }

    public function test_bulk_retry_redispatches_every_job_in_the_group(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $uuidA = '550e8400-e29b-41d4-a716-446655440001';
        $uuidB = '550e8400-e29b-41d4-a716-446655440002';
        $fingerprint = $this->seedFailureGroupForUuid($uuidA, 'App\\Jobs\\A', new \RuntimeException('Same'));
        $this->seedFailureGroupForUuid($uuidB, 'App\\Jobs\\A', new \RuntimeException('Same'), fingerprintOverride: $fingerprint);

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushRaw')->times(2)->andReturn('ok');
        $factory = Mockery::mock(QueueFactory::class);
        $factory->shouldReceive('connection')->with('redis')->times(2)->andReturn($queue);
        $this->app->instance(QueueFactory::class, $factory);

        $response = $this->postJson("/jobs-monitor/failures/groups/{$fingerprint}/retry");

        $response->assertStatus(200);
        self::assertSame(2, $response->json('succeeded'));
        self::assertSame(0, $response->json('failed'));
    }

    public function test_bulk_delete_removes_every_job_row_in_the_group(): void
    {
        $uuidA = '550e8400-e29b-41d4-a716-446655440001';
        $uuidB = '550e8400-e29b-41d4-a716-446655440002';
        $fingerprint = $this->seedFailureGroupForUuid($uuidA, 'App\\Jobs\\A', new \RuntimeException('Same'));
        $this->seedFailureGroupForUuid($uuidB, 'App\\Jobs\\A', new \RuntimeException('Same'), fingerprintOverride: $fingerprint);

        $response = $this->postJson("/jobs-monitor/failures/groups/{$fingerprint}/delete");

        $response->assertStatus(200);
        self::assertSame(2, $response->json('succeeded'));
    }

    /**
     * Store a Failed attempt + run fingerprinting. Returns the fingerprint hash.
     */
    private function seedFailureGroupForUuid(
        string $uuid,
        string $jobClass,
        \Throwable $exception,
        ?string $fingerprintOverride = null,
    ): string {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        /** @var StoreJobRecordAction $store */
        $store = $this->app->make(StoreJobRecordAction::class);
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $store(new JobRecordData(
            id: $uuid,
            attempt: 3,
            jobClass: $jobClass,
            connection: 'redis',
            queue: 'default',
            status: JobStatus::Failed,
            startedAt: $now,
            finishedAt: $now,
            exception: $exception::class.': '.$exception->getMessage(),
            payload: [
                'displayName' => $jobClass,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['command' => 'serialized'],
            ],
        ));

        /** @var RecordFailureFingerprintAction $record */
        $record = $this->app->make(RecordFailureFingerprintAction::class);
        $fp = $record(
            id: $uuid,
            attempt: 3,
            jobClass: $jobClass,
            exception: $exception,
            occurredAt: $now,
        );

        return $fingerprintOverride ?? $fp->hash;
    }
}
