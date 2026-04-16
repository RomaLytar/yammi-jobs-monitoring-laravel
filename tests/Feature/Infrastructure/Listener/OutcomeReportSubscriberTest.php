<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Listener;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Mockery;
use Yammi\JobsMonitor\Application\Action\CaptureOutcomeReportAction;
use Yammi\JobsMonitor\Domain\Job\Contract\ReportsOutcome;
use Yammi\JobsMonitor\Domain\Job\Enum\OutcomeStatus;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport;
use Yammi\JobsMonitor\Infrastructure\Listener\OutcomeReportSubscriber;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class OutcomeReportSubscriberTest extends TestCase
{
    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryJobRecordRepository $repository;

    private OutcomeReportSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryJobRecordRepository();
        $this->subscriber = new OutcomeReportSubscriber(
            new CaptureOutcomeReportAction($this->repository),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_captures_outcome_when_job_implements_reports_outcome(): void
    {
        $report = new OutcomeReport(
            processed: 42,
            skipped: 3,
            warnings: ['Partial batch'],
            status: OutcomeStatus::Ok,
        );

        $jobInstance = new OutcomeReportTestJobWithOutcome($report);

        $job = $this->makeJob(uuid: self::UUID, attempts: 1, command: serialize($jobInstance));

        $this->subscriber->handle(new JobProcessed('redis', $job));

        $outcome = $this->repository->outcomeFor(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($outcome);
        self::assertSame(42, $outcome->processed);
        self::assertSame(3, $outcome->skipped);
        self::assertSame(['Partial batch'], $outcome->warnings);
        self::assertSame(OutcomeStatus::Ok, $outcome->status);
    }

    public function test_ignores_jobs_that_do_not_implement_reports_outcome(): void
    {
        $plainJob = new OutcomeReportTestJobWithoutOutcome();
        $job = $this->makeJob(uuid: self::UUID, attempts: 1, command: serialize($plainJob));

        $this->subscriber->handle(new JobProcessed('redis', $job));

        $outcome = $this->repository->outcomeFor(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNull($outcome);
    }

    public function test_ignores_payload_without_serialized_command(): void
    {
        $job = $this->makeJob(uuid: self::UUID, attempts: 1, command: null);

        $this->subscriber->handle(new JobProcessed('redis', $job));

        $outcome = $this->repository->outcomeFor(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNull($outcome);
    }

    public function test_ignores_payload_with_empty_command_string(): void
    {
        $job = $this->makeJob(uuid: self::UUID, attempts: 1, command: '');

        $this->subscriber->handle(new JobProcessed('redis', $job));

        $outcome = $this->repository->outcomeFor(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNull($outcome);
    }

    public function test_ignores_payload_with_unserializable_command(): void
    {
        $job = $this->makeJob(uuid: self::UUID, attempts: 1, command: 'not-a-valid-serialized-string');

        $this->subscriber->handle(new JobProcessed('redis', $job));

        $outcome = $this->repository->outcomeFor(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNull($outcome);
    }

    public function test_silently_catches_exceptions_from_action(): void
    {
        $throwingRepository = Mockery::mock(\Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository::class);
        $throwingRepository->shouldReceive('recordOutcome')
            ->andThrow(new \RuntimeException('DB down'));

        $subscriber = new OutcomeReportSubscriber(
            new CaptureOutcomeReportAction($throwingRepository),
        );

        $report = new OutcomeReport(
            processed: 1,
            skipped: 0,
            warnings: [],
            status: OutcomeStatus::Ok,
        );

        $jobInstance = new OutcomeReportTestJobWithOutcome($report);

        $job = $this->makeJob(uuid: self::UUID, attempts: 1, command: serialize($jobInstance));

        $subscriber->handle(new JobProcessed('redis', $job));

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

    private function makeJob(string $uuid, int $attempts, ?string $command): Job
    {
        $payload = [
            'displayName' => 'App\\Jobs\\SomeJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => $command !== null ? ['command' => $command] : [],
        ];

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('uuid')->andReturn($uuid);
        $job->shouldReceive('attempts')->andReturn($attempts);
        $job->shouldReceive('payload')->andReturn($payload);

        return $job;
    }
}

/**
 * @internal Test double that implements ReportsOutcome.
 */
class OutcomeReportTestJobWithOutcome implements ReportsOutcome
{
    public function __construct(
        private readonly OutcomeReport $report = new OutcomeReport(
            processed: 42,
            skipped: 3,
            warnings: ['Partial batch'],
            status: OutcomeStatus::Ok,
        ),
    ) {}

    public function outcome(): OutcomeReport
    {
        return $this->report;
    }
}

/**
 * @internal Test double that does NOT implement ReportsOutcome.
 */
class OutcomeReportTestJobWithoutOutcome
{
    public function handle(): void {}
}
