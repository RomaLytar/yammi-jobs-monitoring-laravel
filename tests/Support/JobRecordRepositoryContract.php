<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;

/**
 * Behaviour every JobRecordRepository implementation must satisfy.
 *
 * Concrete implementations (in-memory, Eloquent, …) extend this class
 * and provide createRepository(). The same suite then runs against the
 * implementation, guaranteeing identical contract behaviour everywhere.
 */
abstract class JobRecordRepositoryContract extends TestCase
{
    abstract protected function createRepository(): JobRecordRepository;

    public function test_find_returns_null_when_record_does_not_exist(): void
    {
        $repository = $this->createRepository();

        self::assertNull(
            $repository->findByIdentifierAndAttempt(
                new JobIdentifier('550e8400-e29b-41d4-a716-446655440000'),
                Attempt::first(),
            ),
        );
    }

    public function test_save_then_find_returns_the_same_record(): void
    {
        $repository = $this->createRepository();
        $record = $this->makeRecord();

        $repository->save($record);
        $found = $repository->findByIdentifierAndAttempt($record->id, $record->attempt);

        self::assertNotNull($found);
        self::assertSame($record->id->value, $found->id->value);
        self::assertSame($record->attempt->value, $found->attempt->value);
        self::assertSame(JobStatus::Processing, $found->status());
    }

    public function test_save_overwrites_existing_record_with_same_identifier_and_attempt(): void
    {
        $repository = $this->createRepository();
        $record = $this->makeRecord();

        $repository->save($record);
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));
        $repository->save($record);

        $found = $repository->findByIdentifierAndAttempt($record->id, $record->attempt);

        self::assertNotNull($found);
        self::assertSame(JobStatus::Processed, $found->status());
    }

    public function test_records_with_different_attempts_are_stored_independently(): void
    {
        $repository = $this->createRepository();

        $first = $this->makeRecord(Attempt::first());
        $second = $this->makeRecord(new Attempt(2));

        $repository->save($first);
        $repository->save($second);

        $foundFirst = $repository->findByIdentifierAndAttempt($first->id, Attempt::first());
        $foundSecond = $repository->findByIdentifierAndAttempt($first->id, new Attempt(2));

        self::assertNotNull($foundFirst);
        self::assertNotNull($foundSecond);
        self::assertSame(1, $foundFirst->attempt->value);
        self::assertSame(2, $foundSecond->attempt->value);
    }

    private function makeRecord(?Attempt $attempt = null): JobRecord
    {
        return new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440000'),
            attempt: $attempt ?? Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }
}
