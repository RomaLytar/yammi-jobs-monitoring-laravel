<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Listener;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Mockery;
use Yammi\JobsMonitor\Application\Action\DetectDurationAnomalyAction;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\DurationAnomalyKind;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationBaseline;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Infrastructure\Listener\DurationAnomalySubscriber;
use Yammi\JobsMonitor\Tests\Support\InMemoryDurationBaselineRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class DurationAnomalySubscriberTest extends TestCase
{
    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    private const JOB_CLASS = 'App\\Jobs\\ProcessInvoice';

    private InMemoryJobRecordRepository $recordRepository;

    private InMemoryDurationBaselineRepository $baselineRepository;

    private DurationAnomalySubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recordRepository = new InMemoryJobRecordRepository;
        $this->baselineRepository = new InMemoryDurationBaselineRepository;

        $detector = new DetectDurationAnomalyAction(
            repository: $this->baselineRepository,
            minSamples: 10,
            shortFactor: 0.2,
            longFactor: 2.0,
        );

        $this->subscriber = new DurationAnomalySubscriber($detector, $this->recordRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_records_long_anomaly_when_duration_exceeds_baseline(): void
    {
        $now = new DateTimeImmutable;

        $this->storeBaselineWithSamples(p50Ms: 100, p95Ms: 200, samples: 50);
        $this->storeCompletedJobRecord(self::UUID, 1, durationMs: 500, finishedAt: $now);

        $this->subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        $anomalies = $this->baselineRepository->allAnomalies();

        self::assertCount(1, $anomalies);
        self::assertSame(self::UUID, $anomalies[0]->jobUuid);
        self::assertSame(1, $anomalies[0]->attempt);
        self::assertSame(self::JOB_CLASS, $anomalies[0]->jobClass);
        self::assertSame(DurationAnomalyKind::Long, $anomalies[0]->kind);
        self::assertSame(500, $anomalies[0]->durationMs);
    }

    public function test_records_short_anomaly_when_duration_below_baseline(): void
    {
        $now = new DateTimeImmutable;

        $this->storeBaselineWithSamples(p50Ms: 1000, p95Ms: 2000, samples: 50);
        $this->storeCompletedJobRecord(self::UUID, 1, durationMs: 100, finishedAt: $now);

        $this->subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        $anomalies = $this->baselineRepository->allAnomalies();

        self::assertCount(1, $anomalies);
        self::assertSame(DurationAnomalyKind::Short, $anomalies[0]->kind);
        self::assertSame(100, $anomalies[0]->durationMs);
    }

    public function test_no_anomaly_when_duration_within_baseline_range(): void
    {
        $now = new DateTimeImmutable;

        $this->storeBaselineWithSamples(p50Ms: 100, p95Ms: 200, samples: 50);
        $this->storeCompletedJobRecord(self::UUID, 1, durationMs: 150, finishedAt: $now);

        $this->subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        self::assertCount(0, $this->baselineRepository->allAnomalies());
    }

    public function test_no_anomaly_when_baseline_has_too_few_samples(): void
    {
        $now = new DateTimeImmutable;

        $this->storeBaselineWithSamples(p50Ms: 100, p95Ms: 200, samples: 5);
        $this->storeCompletedJobRecord(self::UUID, 1, durationMs: 500, finishedAt: $now);

        $this->subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        self::assertCount(0, $this->baselineRepository->allAnomalies());
    }

    public function test_no_anomaly_when_no_baseline_exists(): void
    {
        $now = new DateTimeImmutable;

        $this->storeCompletedJobRecord(self::UUID, 1, durationMs: 500, finishedAt: $now);

        $this->subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        self::assertCount(0, $this->baselineRepository->allAnomalies());
    }

    public function test_skips_when_job_record_not_found(): void
    {
        $this->storeBaselineWithSamples(p50Ms: 100, p95Ms: 200, samples: 50);

        $this->subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        self::assertCount(0, $this->baselineRepository->allAnomalies());
    }

    public function test_skips_when_job_record_has_no_duration(): void
    {
        $this->storeBaselineWithSamples(p50Ms: 100, p95Ms: 200, samples: 50);
        $this->storeProcessingJobRecord(self::UUID, 1);

        $this->subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        self::assertCount(0, $this->baselineRepository->allAnomalies());
    }

    public function test_silently_catches_exceptions_from_repository(): void
    {
        $throwingRecordRepo = Mockery::mock(\Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository::class);
        $throwingRecordRepo->shouldReceive('findByIdentifierAndAttempt')
            ->andThrow(new \RuntimeException('DB down'));

        $detector = new DetectDurationAnomalyAction(
            repository: $this->baselineRepository,
            minSamples: 10,
            shortFactor: 0.2,
            longFactor: 2.0,
        );

        $subscriber = new DurationAnomalySubscriber($detector, $throwingRecordRepo);

        $subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        $this->expectNotToPerformAssertions();
    }

    public function test_silently_catches_exceptions_from_baseline_repository(): void
    {
        $now = new DateTimeImmutable;
        $this->storeCompletedJobRecord(self::UUID, 1, durationMs: 500, finishedAt: $now);

        $throwingBaseline = Mockery::mock(\Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository::class);
        $throwingBaseline->shouldReceive('findBaseline')
            ->andThrow(new \RuntimeException('Baseline storage crashed'));

        $detector = new DetectDurationAnomalyAction(
            repository: $throwingBaseline,
            minSamples: 10,
            shortFactor: 0.2,
            longFactor: 2.0,
        );

        $subscriber = new DurationAnomalySubscriber($detector, $this->recordRepository);

        $subscriber->handle(
            new JobProcessed('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        $this->expectNotToPerformAssertions();
    }

    public function test_subscribe_returns_event_to_handler_mapping(): void
    {
        $map = $this->subscriber->subscribe(Mockery::mock(Dispatcher::class));

        self::assertSame(
            [JobProcessed::class => 'handle'],
            $map,
        );
    }

    private function storeBaselineWithSamples(int $p50Ms, int $p95Ms, int $samples): void
    {
        $now = new DateTimeImmutable;

        $this->baselineRepository->saveBaseline(new DurationBaseline(
            jobClass: self::JOB_CLASS,
            samplesCount: $samples,
            p50Ms: $p50Ms,
            p95Ms: $p95Ms,
            minMs: (int) floor($p50Ms * 0.5),
            maxMs: $p95Ms * 3,
            computedOverFrom: new DateTimeImmutable('-7 days'),
            computedOverTo: $now,
        ));
    }

    private function storeCompletedJobRecord(
        string $uuid,
        int $attempt,
        int $durationMs,
        DateTimeImmutable $finishedAt,
    ): void {
        $startedAt = $finishedAt->modify(sprintf('-%d milliseconds', $durationMs));

        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: new Attempt($attempt),
            jobClass: self::JOB_CLASS,
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $startedAt,
        );
        $record->markAsProcessed($finishedAt);

        $this->recordRepository->save($record);
    }

    private function storeProcessingJobRecord(string $uuid, int $attempt): void
    {
        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: new Attempt($attempt),
            jobClass: self::JOB_CLASS,
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable,
        );

        $this->recordRepository->save($record);
    }

    private function makeJob(string $uuid, int $attempts): Job
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('uuid')->andReturn($uuid);
        $job->shouldReceive('attempts')->andReturn($attempts);

        return $job;
    }
}
