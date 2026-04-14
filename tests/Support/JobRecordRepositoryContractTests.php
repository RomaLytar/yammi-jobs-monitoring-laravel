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

    public function test_find_paginated_filters_by_queue(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        $defaultQueue = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-5 minutes'),
        );
        $emailsQueue = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: $now->modify('-3 minutes'),
        );

        $repository->save($defaultQueue);
        $repository->save($emailsQueue);

        $results = $repository->findPaginated(null, null, 50, 1, 'started_at', 'desc', null, 'emails');

        self::assertCount(1, $results);
        self::assertSame($emailsQueue->id->value, $results[0]->id->value);
    }

    public function test_find_paginated_filters_by_connection(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        $redis = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-5 minutes'),
        );
        $sqs = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'sqs',
            queue: new QueueName('default'),
            startedAt: $now->modify('-3 minutes'),
        );

        $repository->save($redis);
        $repository->save($sqs);

        $results = $repository->findPaginated(null, null, 50, 1, 'started_at', 'desc', null, null, 'sqs');

        self::assertCount(1, $results);
        self::assertSame($sqs->id->value, $results[0]->id->value);
    }

    public function test_find_paginated_filters_by_failure_category(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        $transient = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-5 minutes'),
        );
        $transient->markAsFailed($now->modify('-4 minutes'), 'timeout', FailureCategory::Transient);

        $permanent = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-3 minutes'),
        );
        $permanent->markAsFailed($now->modify('-2 minutes'), 'validation', FailureCategory::Permanent);

        $repository->save($transient);
        $repository->save($permanent);

        $results = $repository->findPaginated(
            null,
            null,
            50,
            1,
            'started_at',
            'desc',
            null,
            null,
            null,
            FailureCategory::Permanent,
        );

        self::assertCount(1, $results);
        self::assertSame($permanent->id->value, $results[0]->id->value);
    }

    public function test_count_filtered_respects_queue_and_connection(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        $a = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: $now->modify('-5 minutes'),
        );
        $b = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'sqs',
            queue: new QueueName('emails'),
            startedAt: $now->modify('-3 minutes'),
        );

        $repository->save($a);
        $repository->save($b);

        self::assertSame(2, $repository->countFiltered(null, null));
        self::assertSame(1, $repository->countFiltered(null, null, null, 'emails', 'redis'));
    }

    public function test_distinct_queues_returns_unique_queue_names(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        foreach (['default', 'emails', 'default', 'reports'] as $i => $queue) {
            $repository->save(new JobRecord(
                id: new JobIdentifier(sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i + 1)),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: 'redis',
                queue: new QueueName($queue),
                startedAt: $now->modify("-{$i} minutes"),
            ));
        }

        $queues = $repository->distinctQueues();
        sort($queues);

        self::assertSame(['default', 'emails', 'reports'], $queues);
    }

    public function test_distinct_connections_returns_unique_connection_names(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        foreach (['redis', 'sqs', 'redis', 'database'] as $i => $connection) {
            $repository->save(new JobRecord(
                id: new JobIdentifier(sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i + 1)),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: $connection,
                queue: new QueueName('default'),
                startedAt: $now->modify("-{$i} minutes"),
            ));
        }

        $connections = $repository->distinctConnections();
        sort($connections);

        self::assertSame(['database', 'redis', 'sqs'], $connections);
    }

    public function test_find_dead_letter_jobs_returns_permanent_or_critical_failures(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        // Transient failure — NOT dead letter (retryable)
        $transient = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440001', $now->modify('-5 minutes'));
        $transient->markAsFailed($now->modify('-4 minutes'), 'timeout', FailureCategory::Transient);

        // Permanent failure — IS dead letter
        $permanent = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440002', $now->modify('-4 minutes'));
        $permanent->markAsFailed($now->modify('-3 minutes'), 'validation', FailureCategory::Permanent);

        // Critical failure — IS dead letter
        $critical = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440003', $now->modify('-3 minutes'));
        $critical->markAsFailed($now->modify('-2 minutes'), 'class not found', FailureCategory::Critical);

        $repository->save($transient);
        $repository->save($permanent);
        $repository->save($critical);

        $dead = $repository->findDeadLetterJobs(50, 1, 3);

        self::assertCount(2, $dead);
        $uuids = array_map(static fn ($r) => $r->id->value, $dead);
        self::assertContains('550e8400-e29b-41d4-a716-446655440002', $uuids);
        self::assertContains('550e8400-e29b-41d4-a716-446655440003', $uuids);
        self::assertNotContains('550e8400-e29b-41d4-a716-446655440001', $uuids);
    }

    public function test_find_dead_letter_jobs_includes_attempts_exceeding_max_tries(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        // Only 1 attempt, transient — NOT dead letter (can still retry)
        $a = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440001', $now->modify('-5 minutes'));
        $a->markAsFailed($now->modify('-4 minutes'), 'timeout', FailureCategory::Transient);

        // 3 attempts (= max_tries), transient — IS dead letter (exhausted retries)
        $exhausted = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: new Attempt(3),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-3 minutes'),
        );
        $exhausted->markAsFailed($now->modify('-2 minutes'), 'timeout', FailureCategory::Transient);

        $repository->save($a);
        $repository->save($exhausted);

        $dead = $repository->findDeadLetterJobs(50, 1, 3);

        self::assertCount(1, $dead);
        self::assertSame('550e8400-e29b-41d4-a716-446655440002', $dead[0]->id->value);
    }

    public function test_find_dead_letter_jobs_ignores_processed_records(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        // Failed attempt 1
        $first = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440001', $now->modify('-5 minutes'));
        $first->markAsFailed($now->modify('-4 minutes'), 'timeout', FailureCategory::Transient);

        // Attempt 2 of the SAME uuid succeeded — so the UUID is NOT dead letter
        $second = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-3 minutes'),
        );
        $second->markAsProcessed($now->modify('-2 minutes'));

        $repository->save($first);
        $repository->save($second);

        $dead = $repository->findDeadLetterJobs(50, 1, 3);

        self::assertCount(0, $dead);
    }

    public function test_count_dead_letter_jobs_matches_find(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        foreach ([
            ['550e8400-e29b-41d4-a716-446655440001', FailureCategory::Permanent],
            ['550e8400-e29b-41d4-a716-446655440002', FailureCategory::Critical],
            ['550e8400-e29b-41d4-a716-446655440003', FailureCategory::Transient],
        ] as [$uuid, $cat]) {
            $r = $this->makeContractRecordWith($uuid, $now->modify('-5 minutes'));
            $r->markAsFailed($now->modify('-4 minutes'), 'boom', $cat);
            $repository->save($r);
        }

        self::assertSame(2, $repository->countDeadLetterJobs(3));
    }

    public function test_delete_by_identifier_removes_all_attempts_for_that_uuid(): void
    {
        $repository = $this->createRepository();
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $now = new DateTimeImmutable;

        foreach ([1, 2, 3] as $i) {
            $r = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: new Attempt($i),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $now->modify("-{$i} minutes"),
            );
            $r->markAsFailed($now->modify("-{$i} minutes +10 seconds"), 'boom');
            $repository->save($r);
        }

        // Another UUID — should be untouched
        $other = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440002', $now);
        $repository->save($other);

        $deleted = $repository->deleteByIdentifier(new JobIdentifier($uuid));

        self::assertSame(3, $deleted);
        self::assertSame([], $repository->findAllAttempts(new JobIdentifier($uuid)));
        self::assertCount(1, $repository->findAllAttempts(new JobIdentifier('550e8400-e29b-41d4-a716-446655440002')));
    }

    public function test_aggregate_stats_by_class_multi_groups_all_classes(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        // Two SendInvoice — one processed, one failed
        $a = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440001', $now->modify('-10 minutes'));
        $a->markAsProcessed($now->modify('-9 minutes'));
        $b = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440002', $now->modify('-8 minutes'));
        $b->markAsFailed($now->modify('-7 minutes'), 'timeout');

        // One ProcessPayment — processed, 2nd attempt (so retryCount counts)
        $c = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440003'),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\ProcessPayment',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-5 minutes'),
        );
        $c->markAsProcessed($now->modify('-4 minutes'));

        $repository->save($a);
        $repository->save($b);
        $repository->save($c);

        $stats = $repository->aggregateStatsByClassMulti(null);

        // Normalise by class for easy lookups
        $byClass = [];
        foreach ($stats as $row) {
            $byClass[$row['job_class']] = $row;
        }

        self::assertArrayHasKey('App\\Jobs\\SendInvoice', $byClass);
        self::assertArrayHasKey('App\\Jobs\\ProcessPayment', $byClass);

        self::assertSame(2, $byClass['App\\Jobs\\SendInvoice']['total']);
        self::assertSame(1, $byClass['App\\Jobs\\SendInvoice']['processed']);
        self::assertSame(1, $byClass['App\\Jobs\\SendInvoice']['failed']);
        self::assertIsFloat($byClass['App\\Jobs\\SendInvoice']['avg_duration_ms']);
        self::assertSame(0, $byClass['App\\Jobs\\SendInvoice']['retry_count']);

        self::assertSame(1, $byClass['App\\Jobs\\ProcessPayment']['total']);
        self::assertSame(1, $byClass['App\\Jobs\\ProcessPayment']['processed']);
        self::assertSame(1, $byClass['App\\Jobs\\ProcessPayment']['retry_count']);
    }

    public function test_aggregate_stats_by_class_multi_respects_since_filter(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        $old = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440001', $now->modify('-2 hours'));
        $old->markAsProcessed($now->modify('-119 minutes'));

        $recent = $this->makeContractRecordWith('550e8400-e29b-41d4-a716-446655440002', $now->modify('-10 minutes'));
        $recent->markAsProcessed($now->modify('-9 minutes'));

        $repository->save($old);
        $repository->save($recent);

        $stats = $repository->aggregateStatsByClassMulti($now->modify('-1 hour'));

        self::assertCount(1, $stats);
        self::assertSame(1, $stats[0]['total']);
    }

    public function test_aggregate_stats_by_class_multi_returns_empty_when_no_records(): void
    {
        $repository = $this->createRepository();

        self::assertSame([], $repository->aggregateStatsByClassMulti(null));
    }

    public function test_find_all_attempts_returns_all_records_for_same_uuid_ordered_by_attempt(): void
    {
        $repository = $this->createRepository();
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $now = new DateTimeImmutable;

        $first = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: new Attempt(1),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-30 minutes'),
        );
        $first->markAsFailed($now->modify('-29 minutes'), 'timeout');

        $second = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-20 minutes'),
        );
        $second->markAsFailed($now->modify('-19 minutes'), 'timeout again');

        $third = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: new Attempt(3),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-10 minutes'),
        );
        $third->markAsProcessed($now->modify('-9 minutes'));

        // Save in reverse order to verify sorting is done by the repository, not insertion order.
        $repository->save($third);
        $repository->save($first);
        $repository->save($second);

        $attempts = $repository->findAllAttempts(new JobIdentifier($uuid));

        self::assertCount(3, $attempts);
        self::assertSame(1, $attempts[0]->attempt->value);
        self::assertSame(2, $attempts[1]->attempt->value);
        self::assertSame(3, $attempts[2]->attempt->value);
    }

    public function test_find_all_attempts_returns_empty_array_for_unknown_uuid(): void
    {
        $repository = $this->createRepository();

        $attempts = $repository->findAllAttempts(
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440099'),
        );

        self::assertSame([], $attempts);
    }

    public function test_find_all_attempts_does_not_include_other_uuids(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable;

        $a = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-10 minutes'),
        );
        $b = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-5 minutes'),
        );

        $repository->save($a);
        $repository->save($b);

        $attempts = $repository->findAllAttempts(
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
        );

        self::assertCount(1, $attempts);
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $attempts[0]->id->value);
    }

    public function test_delete_older_than_removes_old_records_and_returns_count(): void
    {
        $repository = $this->createRepository();

        $old = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2025-01-01T00:00:00Z'),
        );
        $recent = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-06-01T00:00:00Z'),
        );

        $repository->save($old);
        $repository->save($recent);

        $deleted = $repository->deleteOlderThan(new DateTimeImmutable('2026-01-01T00:00:00Z'));

        self::assertSame(1, $deleted);
        self::assertNull(
            $repository->findByIdentifierAndAttempt($old->id, $old->attempt),
        );
        self::assertNotNull(
            $repository->findByIdentifierAndAttempt($recent->id, $recent->attempt),
        );
    }

    public function test_delete_older_than_returns_zero_when_nothing_to_delete(): void
    {
        $repository = $this->createRepository();

        $recent = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-06-01T00:00:00Z'),
        );

        $repository->save($recent);

        $deleted = $repository->deleteOlderThan(new DateTimeImmutable('2025-01-01T00:00:00Z'));

        self::assertSame(0, $deleted);
    }

    public function test_count_failures_since_ignores_non_failed_records(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $processing = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-1 minute'),
        );

        $processed = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-2 minutes'),
        );
        $processed->markAsProcessed($now->modify('-1 minute'));

        $failed = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440003',
            $now->modify('-3 minutes'),
        );
        $failed->markAsFailed($now->modify('-2 minutes'), 'boom');

        $repository->save($processing);
        $repository->save($processed);
        $repository->save($failed);

        self::assertSame(1, $repository->countFailuresSince($now->modify('-5 minutes')));
    }

    public function test_count_failures_since_excludes_records_outside_window(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $inWindow = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-2 minutes'),
        );
        $inWindow->markAsFailed($now->modify('-1 minute'), 'boom');

        $outsideWindow = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-1 hour'),
        );
        $outsideWindow->markAsFailed($now->modify('-30 minutes'), 'boom');

        $repository->save($inWindow);
        $repository->save($outsideWindow);

        self::assertSame(1, $repository->countFailuresSince($now->modify('-5 minutes')));
    }

    public function test_count_failures_since_uses_finished_at_not_started_at(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        // Started 20 minutes ago but failed 2 minutes ago — should count
        // inside a 5-minute window because "failed recently" is about
        // the failure time, not the start time.
        $longRunning = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-20 minutes'),
        );
        $longRunning->markAsFailed($now->modify('-2 minutes'), 'boom');

        $repository->save($longRunning);

        self::assertSame(1, $repository->countFailuresSince($now->modify('-5 minutes')));
    }

    public function test_count_failures_by_category_since_filters_by_category(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $transient = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            $now->modify('-3 minutes'),
        );
        $transient->markAsFailed($now->modify('-2 minutes'), 'timeout', FailureCategory::Transient);

        $critical = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            $now->modify('-3 minutes'),
        );
        $critical->markAsFailed($now->modify('-1 minute'), 'class missing', FailureCategory::Critical);

        $uncategorized = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440003',
            $now->modify('-2 minutes'),
        );
        $uncategorized->markAsFailed($now->modify('-1 minute'), 'mystery');

        $repository->save($transient);
        $repository->save($critical);
        $repository->save($uncategorized);

        $since = $now->modify('-5 minutes');

        self::assertSame(1, $repository->countFailuresByCategorySince(FailureCategory::Critical, $since));
        self::assertSame(1, $repository->countFailuresByCategorySince(FailureCategory::Transient, $since));
        self::assertSame(0, $repository->countFailuresByCategorySince(FailureCategory::Permanent, $since));
    }

    public function test_count_failures_by_class_since_filters_by_job_class(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $invoice1 = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-3 minutes'),
        );
        $invoice1->markAsFailed($now->modify('-2 minutes'), 'boom');

        $invoice2 = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $invoice2->markAsFailed($now->modify('-1 minute'), 'boom');

        $reportFailed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440003'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\GenerateReport',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $reportFailed->markAsFailed($now->modify('-1 minute'), 'boom');

        $repository->save($invoice1);
        $repository->save($invoice2);
        $repository->save($reportFailed);

        $since = $now->modify('-5 minutes');

        self::assertSame(2, $repository->countFailuresByClassSince('App\\Jobs\\SendInvoice', $since));
        self::assertSame(1, $repository->countFailuresByClassSince('App\\Jobs\\GenerateReport', $since));
        self::assertSame(0, $repository->countFailuresByClassSince('App\\Jobs\\Unknown', $since));
    }

    public function test_count_failures_since_honors_min_attempt_filter(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        // 3 first-attempt failures + 2 second-attempt failures
        for ($i = 0; $i < 3; $i++) {
            $r = new JobRecord(
                id: new JobIdentifier(sprintf('550e8400-e29b-41d4-a716-4466554410%02d', $i)),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\X',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $now->modify('-2 minutes'),
            );
            $r->markAsFailed($now->modify('-1 minute'), 'boom');
            $repository->save($r);
        }
        for ($i = 0; $i < 2; $i++) {
            $r = new JobRecord(
                id: new JobIdentifier(sprintf('550e8400-e29b-41d4-a716-4466554420%02d', $i)),
                attempt: new Attempt(2),
                jobClass: 'App\\Jobs\\X',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $now->modify('-2 minutes'),
            );
            $r->markAsFailed($now->modify('-1 minute'), 'boom');
            $repository->save($r);
        }

        $since = $now->modify('-5 minutes');

        self::assertSame(5, $repository->countFailuresSince($since));
        self::assertSame(5, $repository->countFailuresSince($since, 1));
        self::assertSame(2, $repository->countFailuresSince($since, 2));
        self::assertSame(0, $repository->countFailuresSince($since, 3));
    }

    public function test_count_failures_by_category_honors_min_attempt(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $firstAttempt = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655441001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\X',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $firstAttempt->markAsFailed($now->modify('-1 minute'), 'boom', FailureCategory::Transient);

        $secondAttempt = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655441002'),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\X',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $secondAttempt->markAsFailed($now->modify('-1 minute'), 'boom', FailureCategory::Transient);

        $repository->save($firstAttempt);
        $repository->save($secondAttempt);

        $since = $now->modify('-5 minutes');

        self::assertSame(2, $repository->countFailuresByCategorySince(FailureCategory::Transient, $since));
        self::assertSame(1, $repository->countFailuresByCategorySince(FailureCategory::Transient, $since, 2));
    }

    public function test_count_failures_by_class_honors_min_attempt(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $firstAttempt = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655441001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\Target',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $firstAttempt->markAsFailed($now->modify('-1 minute'), 'boom');

        $secondAttempt = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655441002'),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\Target',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $secondAttempt->markAsFailed($now->modify('-1 minute'), 'boom');

        $repository->save($firstAttempt);
        $repository->save($secondAttempt);

        $since = $now->modify('-5 minutes');

        self::assertSame(2, $repository->countFailuresByClassSince('App\\Jobs\\Target', $since));
        self::assertSame(1, $repository->countFailuresByClassSince('App\\Jobs\\Target', $since, 2));
    }

    public function test_find_failure_samples_returns_newest_first_up_to_limit(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        // 5 failures at increasing times
        for ($i = 0; $i < 5; $i++) {
            $r = new JobRecord(
                id: new JobIdentifier(sprintf('550e8400-e29b-41d4-a716-4466554420%02d', $i)),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\X',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: $now->modify("-{$i} minutes"),
            );
            $r->markAsFailed(
                $now->modify('-'.(5 - $i).' seconds'),
                'boom #'.$i,
            );
            $repository->save($r);
        }

        $samples = $repository->findFailureSamples($now->modify('-1 hour'), 3);

        self::assertCount(3, $samples);
        // Newest first: index 4 was finished at -1s, index 3 at -2s, ...
        self::assertSame('boom #4', $samples[0]->exception());
        self::assertSame('boom #3', $samples[1]->exception());
        self::assertSame('boom #2', $samples[2]->exception());
    }

    public function test_find_failure_samples_filters_by_category(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $critical = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655443001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\X',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $critical->markAsFailed($now->modify('-1 minute'), 'boom', FailureCategory::Critical);

        $transient = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655443002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\X',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $transient->markAsFailed($now->modify('-1 minute'), 'boom', FailureCategory::Transient);

        $repository->save($critical);
        $repository->save($transient);

        $samples = $repository->findFailureSamples(
            $now->modify('-5 minutes'),
            10,
            null,
            FailureCategory::Critical,
        );

        self::assertCount(1, $samples);
        self::assertSame(FailureCategory::Critical, $samples[0]->failureCategory());
    }

    public function test_find_failure_samples_filters_by_job_class_and_min_attempt(): void
    {
        $repository = $this->createRepository();
        $now = new DateTimeImmutable('2026-04-13T12:00:00Z');

        $targetAttempt1 = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655443001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\Target',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $targetAttempt1->markAsFailed($now->modify('-1 minute'), 'boom');

        $targetAttempt2 = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655443002'),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\Target',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $targetAttempt2->markAsFailed($now->modify('-1 minute'), 'boom');

        $other = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655443003'),
            attempt: new Attempt(2),
            jobClass: 'App\\Jobs\\Other',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-2 minutes'),
        );
        $other->markAsFailed($now->modify('-1 minute'), 'boom');

        $repository->save($targetAttempt1);
        $repository->save($targetAttempt2);
        $repository->save($other);

        $samples = $repository->findFailureSamples(
            $now->modify('-5 minutes'),
            10,
            2,
            null,
            'App\\Jobs\\Target',
        );

        self::assertCount(1, $samples);
        self::assertSame(2, $samples[0]->attempt->value);
        self::assertSame('App\\Jobs\\Target', $samples[0]->jobClass);
    }

    public function test_aggregate_time_buckets_groups_by_minute(): void
    {
        $repository = $this->createRepository();

        $a = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-01-01T00:00:10Z'),
        );
        $a->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:11Z'));

        $b = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T00:00:40Z'),
        );
        $b->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:41Z'));

        $c = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440003',
            new DateTimeImmutable('2026-01-01T00:01:05Z'),
        );
        $c->markAsFailed(new DateTimeImmutable('2026-01-01T00:01:06Z'), 'boom');

        $d = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440004',
            new DateTimeImmutable('2026-01-01T00:01:45Z'),
        );
        $d->markAsProcessed(new DateTimeImmutable('2026-01-01T00:01:46Z'));

        $e = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440005',
            new DateTimeImmutable('2026-01-01T00:03:20Z'),
        );
        $e->markAsFailed(new DateTimeImmutable('2026-01-01T00:03:21Z'), 'boom');

        foreach ([$a, $b, $c, $d, $e] as $record) {
            $repository->save($record);
        }

        $buckets = $repository->aggregateTimeBuckets(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            'minute',
        );

        self::assertCount(3, $buckets);

        self::assertSame('2026-01-01T00:00:00Z', $buckets[0]['bucket']);
        self::assertSame(2, $buckets[0]['processed']);
        self::assertSame(0, $buckets[0]['failed']);

        self::assertSame('2026-01-01T00:01:00Z', $buckets[1]['bucket']);
        self::assertSame(1, $buckets[1]['processed']);
        self::assertSame(1, $buckets[1]['failed']);

        self::assertSame('2026-01-01T00:03:00Z', $buckets[2]['bucket']);
        self::assertSame(0, $buckets[2]['processed']);
        self::assertSame(1, $buckets[2]['failed']);
    }

    public function test_aggregate_time_buckets_groups_by_hour(): void
    {
        $repository = $this->createRepository();

        $a = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-01-01T10:05:00Z'),
        );
        $a->markAsProcessed(new DateTimeImmutable('2026-01-01T10:05:01Z'));

        $b = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T10:55:00Z'),
        );
        $b->markAsFailed(new DateTimeImmutable('2026-01-01T10:55:01Z'), 'boom');

        $c = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440003',
            new DateTimeImmutable('2026-01-01T12:30:00Z'),
        );
        $c->markAsProcessed(new DateTimeImmutable('2026-01-01T12:30:01Z'));

        foreach ([$a, $b, $c] as $record) {
            $repository->save($record);
        }

        $buckets = $repository->aggregateTimeBuckets(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            'hour',
        );

        self::assertCount(2, $buckets);
        self::assertSame('2026-01-01T10:00:00Z', $buckets[0]['bucket']);
        self::assertSame(1, $buckets[0]['processed']);
        self::assertSame(1, $buckets[0]['failed']);
        self::assertSame('2026-01-01T12:00:00Z', $buckets[1]['bucket']);
        self::assertSame(1, $buckets[1]['processed']);
        self::assertSame(0, $buckets[1]['failed']);
    }

    public function test_aggregate_time_buckets_groups_by_day(): void
    {
        $repository = $this->createRepository();

        $a = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-01-01T03:00:00Z'),
        );
        $a->markAsProcessed(new DateTimeImmutable('2026-01-01T03:00:01Z'));

        $b = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T23:00:00Z'),
        );
        $b->markAsFailed(new DateTimeImmutable('2026-01-01T23:00:01Z'), 'boom');

        $c = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440003',
            new DateTimeImmutable('2026-01-03T12:00:00Z'),
        );
        $c->markAsProcessed(new DateTimeImmutable('2026-01-03T12:00:01Z'));

        foreach ([$a, $b, $c] as $record) {
            $repository->save($record);
        }

        $buckets = $repository->aggregateTimeBuckets(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            'day',
        );

        self::assertCount(2, $buckets);
        self::assertSame('2026-01-01T00:00:00Z', $buckets[0]['bucket']);
        self::assertSame(1, $buckets[0]['processed']);
        self::assertSame(1, $buckets[0]['failed']);
        self::assertSame('2026-01-03T00:00:00Z', $buckets[1]['bucket']);
        self::assertSame(1, $buckets[1]['processed']);
        self::assertSame(0, $buckets[1]['failed']);
    }

    public function test_aggregate_time_buckets_ignores_records_before_since(): void
    {
        $repository = $this->createRepository();

        $old = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2025-12-31T23:59:00Z'),
        );
        $old->markAsProcessed(new DateTimeImmutable('2025-12-31T23:59:01Z'));

        $inside = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T00:00:10Z'),
        );
        $inside->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:11Z'));

        $repository->save($old);
        $repository->save($inside);

        $buckets = $repository->aggregateTimeBuckets(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            'minute',
        );

        self::assertCount(1, $buckets);
        self::assertSame('2026-01-01T00:00:00Z', $buckets[0]['bucket']);
    }

    public function test_aggregate_time_buckets_ignores_processing_records(): void
    {
        $repository = $this->createRepository();

        $processing = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-01-01T00:00:10Z'),
        );

        $processed = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T00:00:20Z'),
        );
        $processed->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:21Z'));

        $repository->save($processing);
        $repository->save($processed);

        $buckets = $repository->aggregateTimeBuckets(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            'minute',
        );

        self::assertCount(1, $buckets);
        self::assertSame(1, $buckets[0]['processed']);
        self::assertSame(0, $buckets[0]['failed']);
    }

    public function test_aggregate_time_buckets_returns_empty_when_no_matches(): void
    {
        $repository = $this->createRepository();

        $buckets = $repository->aggregateTimeBuckets(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            'minute',
        );

        self::assertSame([], $buckets);
    }

    public function test_aggregate_time_buckets_sorts_ascending(): void
    {
        $repository = $this->createRepository();

        // Save out-of-order to verify sort.
        $late = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440002',
            new DateTimeImmutable('2026-01-01T00:05:00Z'),
        );
        $late->markAsProcessed(new DateTimeImmutable('2026-01-01T00:05:01Z'));

        $early = $this->makeContractRecordWith(
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTimeImmutable('2026-01-01T00:01:00Z'),
        );
        $early->markAsProcessed(new DateTimeImmutable('2026-01-01T00:01:01Z'));

        $repository->save($late);
        $repository->save($early);

        $buckets = $repository->aggregateTimeBuckets(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            'minute',
        );

        self::assertCount(2, $buckets);
        self::assertSame('2026-01-01T00:01:00Z', $buckets[0]['bucket']);
        self::assertSame('2026-01-01T00:05:00Z', $buckets[1]['bucket']);
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
