<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\TestCase;

final class DlqCandidatesTest extends TestCase
{
    public function test_dlq_candidates_returns_every_dead_letter_uuid(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $uuids = [];
        for ($i = 1; $i <= 3; $i++) {
            $uuid = sprintf('550e8400-e29b-41d4-a716-%012d', $i);
            $this->storeDeadLetter($repository, $uuid);
            $uuids[] = $uuid;
        }

        $response = $this->getJson('/jobs-monitor/dlq/bulk/candidates');

        $response->assertOk();
        $response->assertJsonStructure(['ids', 'total', 'truncated']);
        self::assertEqualsCanonicalizing($uuids, $response->json('ids'));
        self::assertSame(3, $response->json('total'));
        self::assertFalse($response->json('truncated'));
    }

    public function test_dlq_candidates_marks_truncated_when_total_exceeds_cap(): void
    {
        config()->set('jobs-monitor.bulk.candidate_limit', 2);

        $repository = $this->app->make(JobRecordRepository::class);
        for ($i = 1; $i <= 5; $i++) {
            $this->storeDeadLetter($repository, sprintf('550e8400-e29b-41d4-a716-%012d', $i));
        }

        $response = $this->getJson('/jobs-monitor/dlq/bulk/candidates');

        $response->assertOk();
        self::assertCount(2, $response->json('ids'));
        self::assertSame(5, $response->json('total'));
        self::assertTrue($response->json('truncated'));
    }

    public function test_failures_candidates_filters_by_queue_and_period(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $emailsUuid = '550e8400-e29b-41d4-a716-446655440011';
        $defaultUuid = '550e8400-e29b-41d4-a716-446655440012';
        $this->storeFailure($repository, $emailsUuid, 'emails');
        $this->storeFailure($repository, $defaultUuid, 'default');

        $response = $this->getJson('/jobs-monitor/failures/bulk/candidates?queue=emails&period=all');

        $response->assertOk();
        $response->assertJsonStructure(['ids', 'total', 'truncated']);
        self::assertSame([$emailsUuid], $response->json('ids'));
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
}
