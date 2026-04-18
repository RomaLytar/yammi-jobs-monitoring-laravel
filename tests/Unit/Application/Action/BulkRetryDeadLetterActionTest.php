<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\BulkRetryDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\RecordingQueueDispatcher;
use Yammi\JobsMonitor\Tests\Support\SequentialUuidGenerator;

final class BulkRetryDeadLetterActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_empty_id_list_returns_zero_result(): void
    {
        $action = $this->makeAction($this->repo());

        $result = $action([]);

        self::assertSame(0, $result->succeeded);
        self::assertSame(0, $result->failed);
        self::assertSame([], $result->errors);
        self::assertSame(0, $result->total());
    }

    public function test_counts_all_successful_retries_with_empty_errors(): void
    {
        $repo = $this->repo();
        $first = $this->storeRetryableJob($repo, '550e8400-e29b-41d4-a716-446655440001');
        $second = $this->storeRetryableJob($repo, '550e8400-e29b-41d4-a716-446655440002');

        $action = $this->makeAction($repo);

        $result = $action([$first, $second]);

        self::assertSame(2, $result->succeeded);
        self::assertSame(0, $result->failed);
        self::assertSame([], $result->errors);
    }

    public function test_missing_record_is_recorded_as_error_without_stopping_batch(): void
    {
        $repo = $this->repo();
        $present = $this->storeRetryableJob($repo, '550e8400-e29b-41d4-a716-446655440001');
        $missing = new JobIdentifier('00000000-0000-0000-0000-000000000000');

        $action = $this->makeAction($repo);

        $result = $action([$missing, $present]);

        self::assertSame(1, $result->succeeded);
        self::assertSame(1, $result->failed);
        self::assertArrayHasKey('00000000-0000-0000-0000-000000000000', $result->errors);
        self::assertStringContainsString('No record found', $result->errors['00000000-0000-0000-0000-000000000000']);
    }

    public function test_job_with_missing_payload_is_recorded_as_error(): void
    {
        $repo = $this->repo();
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
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

        $action = $this->makeAction($repo);

        $result = $action([new JobIdentifier($uuid)]);

        self::assertSame(0, $result->succeeded);
        self::assertSame(1, $result->failed);
        self::assertArrayHasKey($uuid, $result->errors);
        self::assertStringContainsString('payload not stored', $result->errors[$uuid]);
    }

    public function test_large_batch_is_processed_to_completion(): void
    {
        $repo = $this->repo();
        $ids = [];
        for ($i = 1; $i <= 100; $i++) {
            $uuid = sprintf('550e8400-e29b-41d4-a716-%012d', $i);
            $ids[] = $this->storeRetryableJob($repo, $uuid);
        }

        $action = $this->makeAction($repo);

        $result = $action($ids);

        self::assertSame(100, $result->succeeded);
        self::assertSame(0, $result->failed);
        self::assertSame(100, $result->total());
    }

    private function repo(): InMemoryJobRecordRepository
    {
        return new InMemoryJobRecordRepository;
    }

    private function makeAction(InMemoryJobRecordRepository $repo): BulkRetryDeadLetterAction
    {
        return new BulkRetryDeadLetterAction(
            new RetryDeadLetterJobAction($repo, new RecordingQueueDispatcher, new SequentialUuidGenerator),
        );
    }

    private function storeRetryableJob(
        InMemoryJobRecordRepository $repo,
        string $uuid,
    ): JobIdentifier {
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

        return new JobIdentifier($uuid);
    }
}
