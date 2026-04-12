<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Exception\InvalidJobTransition;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;

final class JobRecordTest extends TestCase
{
    private function makeRecord(?DateTimeImmutable $startedAt = null): JobRecord
    {
        return new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440000'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $startedAt ?? new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function test_a_new_record_starts_in_processing_status(): void
    {
        self::assertSame(JobStatus::Processing, $this->makeRecord()->status());
    }

    public function test_a_new_record_has_no_finished_at_duration_or_exception(): void
    {
        $record = $this->makeRecord();

        self::assertNull($record->finishedAt());
        self::assertNull($record->duration());
        self::assertNull($record->exception());
    }

    public function test_constructor_exposes_all_descriptive_fields(): void
    {
        $started = new DateTimeImmutable('2026-01-01T12:34:56Z');
        $record = $this->makeRecord($started);

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $record->id->value);
        self::assertSame(1, $record->attempt->value);
        self::assertSame('App\\Jobs\\SendInvoice', $record->jobClass);
        self::assertSame('redis', $record->connection);
        self::assertSame('default', $record->queue->value);
        self::assertSame($started, $record->startedAt);
    }

    public function test_marking_as_processed_transitions_status_and_computes_duration(): void
    {
        $record = $this->makeRecord(new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $finishedAt = new DateTimeImmutable('2026-01-01T00:00:01Z');

        $record->markAsProcessed($finishedAt);

        self::assertSame(JobStatus::Processed, $record->status());
        self::assertSame($finishedAt, $record->finishedAt());
        self::assertNotNull($record->duration());
        self::assertSame(1000, $record->duration()->milliseconds);
        self::assertNull($record->exception());
    }

    public function test_marking_as_failed_transitions_status_and_stores_exception(): void
    {
        $record = $this->makeRecord(new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $finishedAt = new DateTimeImmutable('2026-01-01T00:00:00.500000Z');

        $record->markAsFailed($finishedAt, 'RuntimeException: connection refused');

        self::assertSame(JobStatus::Failed, $record->status());
        self::assertSame($finishedAt, $record->finishedAt());
        self::assertNotNull($record->duration());
        self::assertSame(500, $record->duration()->milliseconds);
        self::assertSame('RuntimeException: connection refused', $record->exception());
    }

    public function test_cannot_mark_as_processed_twice(): void
    {
        $record = $this->makeRecord();
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));

        $this->expectException(InvalidJobTransition::class);
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:02Z'));
    }

    public function test_cannot_mark_as_processed_after_failure(): void
    {
        $record = $this->makeRecord();
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom');

        $this->expectException(InvalidJobTransition::class);
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:02Z'));
    }

    public function test_cannot_mark_as_failed_twice(): void
    {
        $record = $this->makeRecord();
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom');

        $this->expectException(InvalidJobTransition::class);
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:02Z'), 'boom again');
    }

    public function test_cannot_mark_as_failed_after_success(): void
    {
        $record = $this->makeRecord();
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));

        $this->expectException(InvalidJobTransition::class);
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:02Z'), 'boom');
    }

    public function test_a_new_record_has_no_failure_category(): void
    {
        self::assertNull($this->makeRecord()->failureCategory());
    }

    public function test_marking_as_failed_stores_failure_category(): void
    {
        $record = $this->makeRecord(new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $record->markAsFailed(
            new DateTimeImmutable('2026-01-01T00:00:00.500000Z'),
            'RuntimeException: connection refused',
            FailureCategory::Transient,
        );

        self::assertSame(FailureCategory::Transient, $record->failureCategory());
    }

    public function test_failure_category_defaults_to_null_when_not_provided(): void
    {
        $record = $this->makeRecord(new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $record->markAsFailed(
            new DateTimeImmutable('2026-01-01T00:00:00.500000Z'),
            'RuntimeException: something',
        );

        self::assertNull($record->failureCategory());
    }

    public function test_failure_category_is_null_for_processed_records(): void
    {
        $record = $this->makeRecord(new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));

        self::assertNull($record->failureCategory());
    }
}
