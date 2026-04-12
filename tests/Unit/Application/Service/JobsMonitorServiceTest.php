<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\DTO\JobClassStatsData;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;

final class JobsMonitorServiceTest extends TestCase
{
    private InMemoryJobRecordRepository $repository;

    private JobsMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryJobRecordRepository;
        $this->service = new JobsMonitorService(
            $this->repository,
            new NullMetricsDriver,
        );
    }

    public function test_queue_size_delegates_to_metrics_driver(): void
    {
        self::assertNull($this->service->queueSize('default'));
    }

    public function test_delayed_size_delegates_to_metrics_driver(): void
    {
        self::assertNull($this->service->delayedSize('default'));
    }

    public function test_reserved_size_delegates_to_metrics_driver(): void
    {
        self::assertNull($this->service->reservedSize('default'));
    }

    public function test_recent_jobs_returns_records_from_repository(): void
    {
        $this->repository->save($this->makeRecord('550e8400-e29b-41d4-a716-446655440001'));
        $this->repository->save($this->makeRecord('550e8400-e29b-41d4-a716-446655440002'));

        $results = $this->service->recentJobs(10);

        self::assertCount(2, $results);
        self::assertContainsOnlyInstancesOf(JobRecord::class, $results);
    }

    public function test_recent_failures_returns_only_failed_records(): void
    {
        $now = new DateTimeImmutable;

        $processed = $this->makeRecord('550e8400-e29b-41d4-a716-446655440001', $now);
        $processed->markAsProcessed($now->modify('+1 second'));

        $failed = $this->makeRecord('550e8400-e29b-41d4-a716-446655440002', $now);
        $failed->markAsFailed($now->modify('+1 second'), 'Error');

        $this->repository->save($processed);
        $this->repository->save($failed);

        $results = $this->service->recentFailures(24);

        self::assertCount(1, $results);
        self::assertSame(JobStatus::Failed, $results[0]->status());
    }

    public function test_stats_returns_dto_with_correct_data(): void
    {
        $now = new DateTimeImmutable;

        $a = $this->makeRecord('550e8400-e29b-41d4-a716-446655440001', $now);
        $a->markAsProcessed($now->modify('+2 seconds'));

        $b = $this->makeRecord('550e8400-e29b-41d4-a716-446655440002', $now);
        $b->markAsFailed($now->modify('+1 second'), 'Error');

        $this->repository->save($a);
        $this->repository->save($b);

        $stats = $this->service->stats('App\\Jobs\\SendInvoice');

        self::assertInstanceOf(JobClassStatsData::class, $stats);
        self::assertSame('App\\Jobs\\SendInvoice', $stats->jobClass);
        self::assertSame(2, $stats->total);
        self::assertSame(1, $stats->processed);
        self::assertSame(1, $stats->failed);
        self::assertEqualsWithDelta(0.5, $stats->successRate, 0.01);
        self::assertNotNull($stats->avgDurationMs);
    }

    public function test_stats_returns_zeroes_for_unknown_class(): void
    {
        $stats = $this->service->stats('App\\Jobs\\NonExistent');

        self::assertSame(0, $stats->total);
        self::assertSame(0, $stats->processed);
        self::assertSame(0, $stats->failed);
        self::assertEqualsWithDelta(0.0, $stats->successRate, 0.01);
        self::assertNull($stats->avgDurationMs);
    }

    private function makeRecord(string $uuid, ?DateTimeImmutable $startedAt = null): JobRecord
    {
        return new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $startedAt ?? new DateTimeImmutable,
        );
    }
}
