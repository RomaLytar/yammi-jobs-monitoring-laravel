<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\BulkDeleteDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\BulkRetryDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\RefreshDurationBaselinesAction;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Application\Service\PercentileCalculator;
use Yammi\JobsMonitor\Application\Service\YammiJobsManageService;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\Support\InMemoryDurationBaselineRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\RecordingQueueDispatcher;
use Yammi\JobsMonitor\Tests\Support\SequentialUuidGenerator;

final class YammiJobsManageServiceTest extends TestCase
{
    private InMemoryJobRecordRepository $jobs;

    private InMemoryFailureGroupRepository $groups;

    private YammiJobsManageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobs = new InMemoryJobRecordRepository;
        $this->groups = new InMemoryFailureGroupRepository;

        $retry = new RetryDeadLetterJobAction($this->jobs, new RecordingQueueDispatcher, new SequentialUuidGenerator);
        $bulkRetry = new BulkRetryDeadLetterAction($retry);
        $bulkDelete = new BulkDeleteDeadLetterAction($this->jobs);
        $refresh = new RefreshDurationBaselinesAction(
            new InMemoryDurationBaselineRepository,
            new PercentileCalculator,
        );

        $this->service = new YammiJobsManageService(
            jobs: $this->jobs,
            groups: $this->groups,
            retryDlq: $retry,
            bulkRetryDlq: $bulkRetry,
            bulkDeleteDlq: $bulkDelete,
            refreshBaselines: $refresh,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_retry_dlq_returns_new_uuid(): void
    {
        $this->seedRetryableJob('550e8400-e29b-41d4-a716-446655440001');

        $result = $this->service->retryDlq('550e8400-e29b-41d4-a716-446655440001');

        self::assertIsString($result);
        self::assertNotSame('550e8400-e29b-41d4-a716-446655440001', $result);
    }

    public function test_retry_dlq_with_custom_payload(): void
    {
        $this->seedRetryableJob('550e8400-e29b-41d4-a716-446655440001');

        $result = $this->service->retryDlq(
            '550e8400-e29b-41d4-a716-446655440001',
            ['data' => ['edited' => true]],
        );

        self::assertIsString($result);
    }

    public function test_retry_dlq_bulk_returns_bulk_result(): void
    {
        $this->seedRetryableJob('550e8400-e29b-41d4-a716-446655440001');
        $this->seedRetryableJob('550e8400-e29b-41d4-a716-446655440002');

        $result = $this->service->retryDlqBulk([
            '550e8400-e29b-41d4-a716-446655440001',
            '550e8400-e29b-41d4-a716-446655440002',
        ]);

        self::assertInstanceOf(BulkOperationResult::class, $result);
        self::assertSame(2, $result->succeeded);
    }

    public function test_delete_dlq_removes_entry(): void
    {
        $this->seedRetryableJob('550e8400-e29b-41d4-a716-446655440001');

        $this->service->deleteDlq('550e8400-e29b-41d4-a716-446655440001');

        self::assertNull($this->jobs->findByIdentifierAndAttempt(
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            Attempt::first(),
        ));
    }

    public function test_delete_dlq_bulk_returns_result(): void
    {
        $this->seedRetryableJob('550e8400-e29b-41d4-a716-446655440001');

        $result = $this->service->deleteDlqBulk(['550e8400-e29b-41d4-a716-446655440001']);

        self::assertSame(1, $result->succeeded);
    }

    public function test_retry_failure_group_returns_null_for_unknown_fingerprint(): void
    {
        self::assertNull($this->service->retryFailureGroup('0000000000000000'));
    }

    public function test_retry_failure_group_delegates_via_uuids(): void
    {
        $fingerprint = new FailureFingerprint('0123456789abcdef');
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $this->groups->save(new FailureGroup(
            $fingerprint,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable,
            1,
            ['App\\Jobs\\X'],
            new JobIdentifier($uuid),
            'RuntimeException',
            'boom',
            '#0 stack',
        ));

        $this->seedRetryableJob($uuid);
        $this->jobs->setFingerprint(new JobIdentifier($uuid), Attempt::first(), $fingerprint);

        $result = $this->service->retryFailureGroup('0123456789abcdef');

        self::assertNotNull($result);
        self::assertSame(1, $result->succeeded);
    }

    public function test_delete_failure_group_returns_null_for_unknown(): void
    {
        self::assertNull($this->service->deleteFailureGroup('0000000000000000'));
    }

    public function test_refresh_baselines_returns_count(): void
    {
        $count = $this->service->refreshAnomalyBaselines();

        self::assertIsInt($count);
        self::assertSame(0, $count);
    }

    private function seedRetryableJob(string $uuid): void
    {
        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\X',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->setPayload(['uuid' => $uuid, 'job' => 'App\\Jobs\\X', 'data' => []]);
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $this->jobs->save($record);
    }
}
