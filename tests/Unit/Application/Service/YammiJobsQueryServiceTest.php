<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\DTO\PagedResult;
use Yammi\JobsMonitor\Application\Service\YammiJobsQueryService;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Domain\Shared\ValueObject\Period;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryWorkerRepository;

final class YammiJobsQueryServiceTest extends TestCase
{
    private InMemoryJobRecordRepository $jobs;

    private InMemoryFailureGroupRepository $groups;

    private FakeScheduledTaskRunRepository $scheduled;

    private InMemoryWorkerRepository $workers;

    private YammiJobsQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobs = new InMemoryJobRecordRepository;
        $this->groups = new InMemoryFailureGroupRepository;
        $this->scheduled = new FakeScheduledTaskRunRepository;
        $this->workers = new InMemoryWorkerRepository;
        $this->service = new YammiJobsQueryService(
            $this->jobs,
            $this->groups,
            $this->scheduled,
            $this->workers,
            new NullMetricsDriver,
        );
    }

    public function test_jobs_returns_paged_result_with_default_per_page(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->jobs->save($this->record(sprintf('550e8400-e29b-41d4-a716-44665544000%d', $i)));
        }

        $result = $this->service->jobs();

        self::assertInstanceOf(PagedResult::class, $result);
        self::assertCount(3, $result->items);
        self::assertSame(3, $result->total);
        self::assertSame(1, $result->page);
        self::assertSame(50, $result->perPage);
    }

    public function test_jobs_respects_page_and_per_page(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->jobs->save($this->record(sprintf('550e8400-e29b-41d4-a716-44665544000%d', $i)));
        }

        $result = $this->service->jobs(page: 2, perPage: 2);

        self::assertCount(2, $result->items);
        self::assertSame(5, $result->total);
        self::assertSame(2, $result->page);
        self::assertSame(2, $result->perPage);
    }

    public function test_jobs_filters_by_period(): void
    {
        $old = $this->record('550e8400-e29b-41d4-a716-446655440001', new DateTimeImmutable('-2 days'));
        $fresh = $this->record('550e8400-e29b-41d4-a716-446655440002', new DateTimeImmutable('-10 minutes'));
        $this->jobs->save($old);
        $this->jobs->save($fresh);

        $result = $this->service->jobs(period: '1h');

        self::assertCount(1, $result->items);
        self::assertSame($fresh->id->value, $result->items[0]->id->value);
    }

    public function test_jobs_accepts_period_value_object(): void
    {
        $this->jobs->save($this->record('550e8400-e29b-41d4-a716-446655440001'));

        $result = $this->service->jobs(period: Period::last('1h'));

        self::assertCount(1, $result->items);
    }

    public function test_jobs_accepts_period_null_for_all_time(): void
    {
        $old = $this->record('550e8400-e29b-41d4-a716-446655440001', new DateTimeImmutable('-30 days'));
        $this->jobs->save($old);

        $result = $this->service->jobs(period: null);

        self::assertCount(1, $result->items);
    }

    public function test_failed_returns_only_failed_records(): void
    {
        $now = new DateTimeImmutable;

        $ok = $this->record('550e8400-e29b-41d4-a716-446655440001', $now);
        $ok->markAsProcessed($now->modify('+1 second'));

        $fail = $this->record('550e8400-e29b-41d4-a716-446655440002', $now);
        $fail->markAsFailed($now->modify('+1 second'), 'boom');

        $this->jobs->save($ok);
        $this->jobs->save($fail);

        $result = $this->service->failed();

        self::assertSame(1, $result->total);
        self::assertSame(JobStatus::Failed, $result->items[0]->status());
    }

    public function test_attempts_returns_all_attempts_for_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $this->jobs->save(new JobRecord(
            new JobIdentifier($uuid),
            Attempt::first(),
            'App\\Jobs\\X',
            'redis',
            new QueueName('default'),
            new DateTimeImmutable,
        ));
        $this->jobs->save(new JobRecord(
            new JobIdentifier($uuid),
            new Attempt(2),
            'App\\Jobs\\X',
            'redis',
            new QueueName('default'),
            new DateTimeImmutable,
        ));

        $attempts = $this->service->attempts($uuid);

        self::assertCount(2, $attempts);
    }

    public function test_job_returns_single_attempt_or_null(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $this->jobs->save($this->record($uuid));

        self::assertNotNull($this->service->job($uuid, 1));
        self::assertNull($this->service->job($uuid, 99));
    }

    public function test_failure_groups_returns_paged_result(): void
    {
        $this->groups->save(new FailureGroup(
            new FailureFingerprint('0123456789abcdef'),
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable,
            3,
            ['App\\Jobs\\X'],
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            'RuntimeException',
            'boom',
            '#0 stack',
        ));

        $result = $this->service->failureGroups();

        self::assertSame(1, $result->total);
        self::assertCount(1, $result->items);
        self::assertInstanceOf(FailureGroup::class, $result->items[0]);
    }

    public function test_queue_metrics_delegate_to_driver(): void
    {
        self::assertNull($this->service->queueSize('default'));
        self::assertNull($this->service->delayedSize('default'));
        self::assertNull($this->service->reservedSize('default'));
    }

    public function test_scheduled_returns_paged_result(): void
    {
        $this->scheduled->seedTotal(42);

        $result = $this->service->scheduled(page: 2, perPage: 10);

        self::assertInstanceOf(PagedResult::class, $result);
        self::assertSame(42, $result->total);
        self::assertSame(2, $result->page);
        self::assertSame(10, $result->perPage);
    }

    public function test_scheduled_status_counts_delegates(): void
    {
        $this->scheduled->statusCounts = ['success' => 3, 'failed' => 1];

        self::assertSame(['success' => 3, 'failed' => 1], $this->service->scheduledStatusCounts());
    }

    public function test_workers_returns_paged_result(): void
    {
        $result = $this->service->workers(perPage: 10);

        self::assertInstanceOf(PagedResult::class, $result);
        self::assertSame(0, $result->total);
    }

    public function test_stats_returns_dto(): void
    {
        $now = new DateTimeImmutable;
        $r = $this->record('550e8400-e29b-41d4-a716-446655440001', $now);
        $r->markAsProcessed($now->modify('+1 second'));
        $this->jobs->save($r);

        $stats = $this->service->stats('App\\Jobs\\SendInvoice');

        self::assertSame(1, $stats->total);
    }

    private function record(string $uuid, ?DateTimeImmutable $startedAt = null): JobRecord
    {
        return new JobRecord(
            new JobIdentifier($uuid),
            Attempt::first(),
            'App\\Jobs\\SendInvoice',
            'redis',
            new QueueName('default'),
            $startedAt ?? new DateTimeImmutable,
        );
    }
}

final class FakeScheduledTaskRunRepository implements ScheduledTaskRunRepository
{
    public int $seededTotal = 0;

    /** @var array<string, int> */
    public array $statusCounts = [];

    public function seedTotal(int $total): void
    {
        $this->seededTotal = $total;
    }

    public function save(ScheduledTaskRun $run): void {}

    public function findRunning(string $mutex, DateTimeImmutable $startedAt): ?ScheduledTaskRun
    {
        return null;
    }

    public function findStuckRunning(DateTimeImmutable $olderThan): iterable
    {
        return [];
    }

    public function countFailedSince(DateTimeImmutable $since): int
    {
        return 0;
    }

    public function countLateSince(DateTimeImmutable $since): int
    {
        return 0;
    }

    public function latestRunPerMutex(): array
    {
        return [];
    }

    public function findPaginated(int $perPage, int $page, array $filters): array
    {
        return ['rows' => [], 'total' => $this->seededTotal];
    }

    public function statusCounts(): array
    {
        return $this->statusCounts;
    }
}
