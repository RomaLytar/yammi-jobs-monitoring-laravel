<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;

/**
 * Behaviour every JobRecordRepository implementation must satisfy.
 *
 * Concrete test classes (in-memory, Eloquent, …) `use` this trait and
 * provide createRepository(). Because it is a trait rather than an
 * abstract base class, in-memory tests can extend
 * `PHPUnit\Framework\TestCase` directly while integration tests can
 * extend a Testbench-aware base — both share the same contract suite.
 */
trait JobRecordRepositoryContractTests
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
        $record = $this->makeContractRecord();

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
        $record = $this->makeContractRecord();

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

        $first = $this->makeContractRecord(Attempt::first());
        $second = $this->makeContractRecord(new Attempt(2));

        $repository->save($first);
        $repository->save($second);

        $foundFirst = $repository->findByIdentifierAndAttempt($first->id, Attempt::first());
        $foundSecond = $repository->findByIdentifierAndAttempt($first->id, new Attempt(2));

        self::assertNotNull($foundFirst);
        self::assertNotNull($foundSecond);
        self::assertSame(1, $foundFirst->attempt->value);
        self::assertSame(2, $foundSecond->attempt->value);
    }

    public function test_find_recent_returns_records_ordered_by_newest_first(): void
    {
        $repository = $this->createRepository();

        $older = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $newer = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T01:00:00Z'),
        );

        $repository->save($older);
        $repository->save($newer);

        $results = $repository->findRecent(10);

        self::assertCount(2, $results);
        self::assertSame($newer->id->value, $results[0]->id->value);
        self::assertSame($older->id->value, $results[1]->id->value);
    }

    public function test_find_recent_respects_limit(): void
    {
        $repository = $this->createRepository();

        for ($i = 1; $i <= 3; $i++) {
            $repository->save($this->makeContractRecordWith(
                sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i),
                new DateTimeImmutable("2026-01-01T00:0{$i}:00Z"),
            ));
        }

        $results = $repository->findRecent(2);

        self::assertCount(2, $results);
    }

    public function test_find_recent_failures_returns_only_failed_records(): void
    {
        $repository = $this->createRepository();

        $now = new DateTimeImmutable;

        $processing = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-10 minutes'),
        );

        $processed = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-5 minutes'),
        );
        $processed->markAsProcessed($now->modify('-4 minutes'));

        $failed = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440003',
            $now->modify('-2 minutes'),
        );
        $failed->markAsFailed($now->modify('-1 minute'), 'Error');

        $repository->save($processing);
        $repository->save($processed);
        $repository->save($failed);

        $results = $repository->findRecentFailures(24);

        self::assertCount(1, $results);
        self::assertSame(JobStatus::Failed, $results[0]->status());
    }

    public function test_aggregate_stats_by_class_returns_correct_counts(): void
    {
        $repository = $this->createRepository();

        $a = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $a->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:02Z'));

        $b = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T00:01:00Z'),
        );
        $b->markAsFailed(new DateTimeImmutable('2026-01-01T00:01:01Z'), 'Error');

        $c = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440003',
            new DateTimeImmutable('2026-01-01T00:02:00Z'),
        );
        $c->markAsProcessed(new DateTimeImmutable('2026-01-01T00:02:03Z'));

        $repository->save($a);
        $repository->save($b);
        $repository->save($c);

        $stats = $repository->aggregateStatsByClass('App\\Jobs\\SendInvoice');

        self::assertSame(3, $stats['total']);
        self::assertSame(2, $stats['processed']);
        self::assertSame(1, $stats['failed']);
        self::assertIsFloat($stats['avg_duration_ms']);
    }

    public function test_aggregate_stats_by_class_returns_zeroes_when_no_records(): void
    {
        $repository = $this->createRepository();

        $stats = $repository->aggregateStatsByClass('App\\Jobs\\NonExistent');

        self::assertSame(0, $stats['total']);
        self::assertSame(0, $stats['processed']);
        self::assertSame(0, $stats['failed']);
        self::assertNull($stats['avg_duration_ms']);
    }

    public function test_find_paginated_returns_records_within_period(): void
    {
        $repository = $this->createRepository();

        $now = new DateTimeImmutable;

        $old = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-2 hours'),
        );
        $recent = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-10 minutes'),
        );

        $repository->save($old);
        $repository->save($recent);

        $results = $repository->findPaginated($now->modify('-1 hour'), null, 50, 1);

        self::assertCount(1, $results);
        self::assertSame($recent->id->value, $results[0]->id->value);
    }

    public function test_find_paginated_filters_by_job_class(): void
    {
        $repository = $this->createRepository();

        $now = new DateTimeImmutable;

        $invoice = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-5 minutes'),
        );
        $payment = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\ProcessPayment',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-3 minutes'),
        );

        $repository->save($invoice);
        $repository->save($payment);

        $results = $repository->findPaginated(null, 'Payment', 50, 1);

        self::assertCount(1, $results);
        self::assertSame($payment->id->value, $results[0]->id->value);
    }

    public function test_find_paginated_respects_page_and_per_page(): void
    {
        $repository = $this->createRepository();

        $now = new DateTimeImmutable;

        for ($i = 1; $i <= 5; $i++) {
            $repository->save($this->makeContractRecordWith(
                sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i),
                $now->modify("-{$i} minutes"),
            ));
        }

        $page1 = $repository->findPaginated(null, null, 2, 1);
        $page2 = $repository->findPaginated(null, null, 2, 2);
        $page3 = $repository->findPaginated(null, null, 2, 3);

        self::assertCount(2, $page1);
        self::assertCount(2, $page2);
        self::assertCount(1, $page3);
    }

    public function test_find_paginated_returns_all_when_since_is_null(): void
    {
        $repository = $this->createRepository();

        $repository->save($this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2020-01-01T00:00:00Z'),
        ));
        $repository->save($this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));

        $results = $repository->findPaginated(null, null, 50, 1);

        self::assertCount(2, $results);
    }

    public function test_count_filtered_returns_total_matching(): void
    {
        $repository = $this->createRepository();

        $now = new DateTimeImmutable;

        $repository->save($this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-2 hours'),
        ));
        $repository->save($this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-10 minutes'),
        ));

        self::assertSame(2, $repository->countFiltered(null, null));
        self::assertSame(1, $repository->countFiltered($now->modify('-1 hour'), null));
    }

    public function test_status_counts_returns_correct_breakdown(): void
    {
        $repository = $this->createRepository();

        $now = new DateTimeImmutable;

        $processing = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-10 minutes'),
        );

        $processed = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-5 minutes'),
        );
        $processed->markAsProcessed($now->modify('-4 minutes'));

        $failed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440003'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\ProcessPayment',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $failed->markAsFailed($now->modify('-1 minute'), 'Error');

        $repository->save($processing);
        $repository->save($processed);
        $repository->save($failed);

        $counts = $repository->statusCounts(null, null);

        self::assertSame(3, $counts['total']);
        self::assertSame(1, $counts['processed']);
        self::assertSame(1, $counts['failed']);
        self::assertSame(1, $counts['processing']);
    }

    public function test_status_counts_respects_period_and_search(): void
    {
        $repository = $this->createRepository();

        $now = new DateTimeImmutable;

        $old = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-2 hours'),
        );
        $old->markAsProcessed($now->modify('-119 minutes'));

        $recent = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-5 minutes'),
        );
        $recent->markAsProcessed($now->modify('-4 minutes'));

        $repository->save($old);
        $repository->save($recent);

        $counts = $repository->statusCounts($now->modify('-1 hour'), null);

        self::assertSame(1, $counts['total']);
        self::assertSame(1, $counts['processed']);
        self::assertSame(0, $counts['failed']);
    }

    public function test_save_preserves_failure_category(): void
    {
        $repository = $this->createRepository();
        $record = $this->makeContractRecord();

        $record->markAsFailed(
            new DateTimeImmutable('2026-01-01T00:00:01Z'),
            'RuntimeException: connection refused',
            FailureCategory::Transient,
        );

        $repository->save($record);

        $found = $repository->findByIdentifierAndAttempt($record->id, $record->attempt);

        self::assertNotNull($found);
        self::assertSame(FailureCategory::Transient, $found->failureCategory());
    }

    public function test_save_preserves_null_failure_category(): void
    {
        $repository = $this->createRepository();
        $record = $this->makeContractRecord();

        $record->markAsFailed(
            new DateTimeImmutable('2026-01-01T00:00:01Z'),
            'RuntimeException: something',
        );

        $repository->save($record);

        $found = $repository->findByIdentifierAndAttempt($record->id, $record->attempt);

        self::assertNotNull($found);
        self::assertNull($found->failureCategory());
    }

    private function makeContractRecord(?Attempt $attempt = null): JobRecord
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

    private function makeContractRecordWith(string $uuid, DateTimeImmutable $startedAt): JobRecord
    {
        return new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $startedAt,
        );
    }
}
