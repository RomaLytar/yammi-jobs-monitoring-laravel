<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\RecordingQueueDispatcher;
use Yammi\JobsMonitor\Tests\Support\SequentialUuidGenerator;

final class RetryDeadLetterJobActionTest extends TestCase
{
    public function test_retry_succeeds_and_returns_new_uuid(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $this->storeRetryableJob($repo, $uuid);

        $dispatcher = new RecordingQueueDispatcher;
        $action = new RetryDeadLetterJobAction($repo, $dispatcher, new SequentialUuidGenerator);

        $newUuid = $action(new JobIdentifier($uuid));

        self::assertNotEmpty($newUuid);
        self::assertNotSame($uuid, $newUuid);
        self::assertCount(1, $dispatcher->pushed);
        self::assertSame('redis', $dispatcher->pushed[0]['connection']);
        self::assertSame('default', $dispatcher->pushed[0]['queue']);

        $decoded = json_decode($dispatcher->pushed[0]['payload'], true);
        self::assertSame($newUuid, $decoded['uuid']);
    }

    public function test_retry_with_custom_payload_uses_provided_data(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $uuid = '550e8400-e29b-41d4-a716-446655440002';
        $this->storeRetryableJob($repo, $uuid);

        $dispatcher = new RecordingQueueDispatcher;
        $action = new RetryDeadLetterJobAction($repo, $dispatcher, new SequentialUuidGenerator);
        $customPayload = ['uuid' => $uuid, 'job' => 'App\\Jobs\\SendInvoice', 'data' => ['amount' => 999]];

        $newUuid = $action(new JobIdentifier($uuid), $customPayload);

        $decoded = json_decode($dispatcher->pushed[0]['payload'], true);
        self::assertSame($newUuid, $decoded['uuid']);
        self::assertSame(999, $decoded['data']['amount']);
    }

    public function test_throws_when_no_record_found(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $action = new RetryDeadLetterJobAction($repo, new RecordingQueueDispatcher, new SequentialUuidGenerator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No record found');

        $action(new JobIdentifier('00000000-0000-0000-0000-000000000000'));
    }

    public function test_throws_when_payload_is_missing(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $uuid = '550e8400-e29b-41d4-a716-446655440003';

        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\NoPayload',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $repo->save($record);

        $action = new RetryDeadLetterJobAction($repo, new RecordingQueueDispatcher, new SequentialUuidGenerator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payload not stored');

        $action(new JobIdentifier($uuid));
    }

    public function test_uses_latest_attempt_payload_when_multiple_attempts_exist(): void
    {
        $repo = new InMemoryJobRecordRepository;
        $uuid = '550e8400-e29b-41d4-a716-446655440004';

        $first = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $first->setPayload(['uuid' => $uuid, 'attempt_marker' => 'first']);
        $first->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $repo->save($first);

        $second = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:01:00Z'),
        );
        $second->setPayload(['uuid' => $uuid, 'attempt_marker' => 'second']);
        $second->markAsFailed(new DateTimeImmutable('2026-01-01T00:01:01Z'), 'boom again', FailureCategory::Permanent);
        $repo->save($second);

        $dispatcher = new RecordingQueueDispatcher;
        $action = new RetryDeadLetterJobAction($repo, $dispatcher, new SequentialUuidGenerator);
        $action(new JobIdentifier($uuid));

        $decoded = json_decode($dispatcher->pushed[0]['payload'], true);
        self::assertSame('second', $decoded['attempt_marker']);
    }

    private function storeRetryableJob(InMemoryJobRecordRepository $repo, string $uuid): void
    {
        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->setPayload(['uuid' => $uuid, 'job' => 'App\\Jobs\\SendInvoice', 'data' => []]);
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $repo->save($record);
    }
}
