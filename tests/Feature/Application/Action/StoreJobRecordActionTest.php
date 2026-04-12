<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\StoreJobRecordAction;
use Yammi\JobsMonitor\Application\DTO\JobRecordData;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;

final class StoreJobRecordActionTest extends TestCase
{
    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryJobRecordRepository $repository;

    private StoreJobRecordAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryJobRecordRepository;
        $this->action = new StoreJobRecordAction($this->repository);
    }

    public function test_creates_a_new_record_when_processing_status_and_no_existing_record(): void
    {
        ($this->action)($this->processingData());

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame(JobStatus::Processing, $stored->status());
        self::assertSame('App\\Jobs\\SendInvoice', $stored->jobClass);
        self::assertSame('redis', $stored->connection);
        self::assertSame('default', $stored->queue->value);
    }

    public function test_marks_existing_record_as_processed_when_processed_status_arrives(): void
    {
        ($this->action)($this->processingData());
        ($this->action)($this->processedData());

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame(JobStatus::Processed, $stored->status());
        self::assertNotNull($stored->finishedAt());
        self::assertNotNull($stored->duration());
        self::assertSame(1000, $stored->duration()->milliseconds);
    }

    public function test_marks_existing_record_as_failed_when_failed_status_arrives(): void
    {
        ($this->action)($this->processingData());
        ($this->action)($this->failedData('RuntimeException: connection refused'));

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame(JobStatus::Failed, $stored->status());
        self::assertSame('RuntimeException: connection refused', $stored->exception());
        self::assertNotNull($stored->duration());
    }

    public function test_creates_a_record_on_first_call_even_if_status_is_already_terminal(): void
    {
        // Edge case: a Processed event arrives without a prior Processing
        // (e.g. monitor was disabled when the job started). The action
        // should still record what it can rather than dropping the event.
        ($this->action)($this->processedData());

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame(JobStatus::Processed, $stored->status());
    }

    public function test_records_for_different_attempts_are_stored_independently(): void
    {
        ($this->action)($this->processingData(attempt: 1));
        ($this->action)($this->processingData(attempt: 2));

        $first = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            new Attempt(1),
        );
        $second = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            new Attempt(2),
        );

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(1, $first->attempt->value);
        self::assertSame(2, $second->attempt->value);
    }

    private function processingData(int $attempt = 1): JobRecordData
    {
        return new JobRecordData(
            id: self::UUID,
            attempt: $attempt,
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: 'default',
            status: JobStatus::Processing,
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    private function processedData(int $attempt = 1): JobRecordData
    {
        return new JobRecordData(
            id: self::UUID,
            attempt: $attempt,
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: 'default',
            status: JobStatus::Processed,
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            finishedAt: new DateTimeImmutable('2026-01-01T00:00:01Z'),
        );
    }

    private function failedData(string $exception, int $attempt = 1): JobRecordData
    {
        return new JobRecordData(
            id: self::UUID,
            attempt: $attempt,
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: 'default',
            status: JobStatus::Failed,
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            finishedAt: new DateTimeImmutable('2026-01-01T00:00:00.500000Z'),
            exception: $exception,
        );
    }
}
