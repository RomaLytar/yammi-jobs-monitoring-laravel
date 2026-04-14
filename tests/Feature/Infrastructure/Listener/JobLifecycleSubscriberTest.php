<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Listener;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Mockery;
use RuntimeException;
use Yammi\JobsMonitor\Application\Action\RecordFailureFingerprintAction;
use Yammi\JobsMonitor\Application\Action\StoreJobRecordAction;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Infrastructure\Classifier\PatternBasedFailureClassifier;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeEmailInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeNumbersInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeTimestampInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeUuidInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Service\RuleBasedTraceNormalizer;
use Yammi\JobsMonitor\Infrastructure\Listener\JobLifecycleSubscriber;
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class JobLifecycleSubscriberTest extends TestCase
{
    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryJobRecordRepository $repository;

    private InMemoryFailureGroupRepository $groupRepository;

    private JobLifecycleSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryJobRecordRepository;
        $this->groupRepository = new InMemoryFailureGroupRepository;

        $normalizer = new RuleBasedTraceNormalizer(rules: [
            new NormalizeUuidInMessageRule,
            new NormalizeEmailInMessageRule,
            new NormalizeTimestampInMessageRule,
            new NormalizeNumbersInMessageRule,
        ]);

        $this->subscriber = new JobLifecycleSubscriber(
            new StoreJobRecordAction($this->repository, new PatternBasedFailureClassifier),
            new RecordFailureFingerprintAction($normalizer, $this->groupRepository, $this->repository),
            new \Yammi\JobsMonitor\Application\Service\PayloadRedactor,
            false,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_processing_event_creates_a_record_in_processing_status(): void
    {
        $this->subscriber->handleJobProcessing(
            new JobProcessing('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

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

    public function test_processed_event_marks_existing_record_as_processed(): void
    {
        $job = $this->makeJob(uuid: self::UUID, attempts: 1);

        $this->subscriber->handleJobProcessing(new JobProcessing('redis', $job));
        $this->subscriber->handleJobProcessed(new JobProcessed('redis', $job));

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame(JobStatus::Processed, $stored->status());
        self::assertNotNull($stored->finishedAt());
    }

    public function test_failed_event_marks_existing_record_as_failed_with_exception_string(): void
    {
        $job = $this->makeJob(uuid: self::UUID, attempts: 1);

        $this->subscriber->handleJobProcessing(new JobProcessing('redis', $job));
        $this->subscriber->handleJobFailed(
            new JobFailed('redis', $job, new RuntimeException('connection refused')),
        );

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame(JobStatus::Failed, $stored->status());
        self::assertNotNull($stored->exception());
        self::assertStringContainsString('RuntimeException', $stored->exception());
        self::assertStringContainsString('connection refused', $stored->exception());
    }

    public function test_exception_event_marks_intermediate_attempt_as_failed(): void
    {
        // Simulates the intermediate retry case: a job that throws but still
        // has retries left. Laravel fires JobExceptionOccurred first, then
        // either JobFailed (final) or JobReleasedAfterException (retry). Our
        // subscriber listens to the exception event so that intermediate
        // failures are captured, not left stuck in "processing" status.
        $job = $this->makeJob(uuid: self::UUID, attempts: 1);

        $this->subscriber->handleJobProcessing(new JobProcessing('redis', $job));
        $this->subscriber->handleJobExceptionOccurred(
            new JobExceptionOccurred('redis', $job, new RuntimeException('connection refused')),
        );

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame(JobStatus::Failed, $stored->status());
        self::assertStringContainsString('connection refused', $stored->exception() ?? '');
    }

    public function test_exception_event_records_a_failure_group_fingerprint(): void
    {
        $this->subscriber->handleJobProcessing(
            new JobProcessing('redis', $this->makeJob(uuid: self::UUID, attempts: 1)),
        );

        $this->subscriber->handleJobExceptionOccurred(
            new JobExceptionOccurred(
                'redis',
                $this->makeJob(uuid: self::UUID, attempts: 1),
                new RuntimeException('Boom 12345'),
            ),
        );

        self::assertSame(1, $this->groupRepository->countAll());

        $fingerprintHash = $this->repository->fingerprintFor(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );
        self::assertNotNull($fingerprintHash);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $fingerprintHash);
    }

    public function test_subscribe_returns_event_to_handler_mapping(): void
    {
        $map = $this->subscriber->subscribe(Mockery::mock(Dispatcher::class));

        self::assertSame(
            [
                JobProcessing::class => 'handleJobProcessing',
                JobProcessed::class => 'handleJobProcessed',
                JobFailed::class => 'handleJobFailed',
                JobExceptionOccurred::class => 'handleJobExceptionOccurred',
            ],
            $map,
        );
    }

    public function test_listeners_are_wired_in_the_container_when_enabled(): void
    {
        // The service provider should have bound the subscriber via the
        // singleton interface so the host application's event dispatcher
        // can resolve it. We replace the bound JobRecordRepository with
        // our in-memory fake so we can assert against it.
        $this->app->instance(JobRecordRepository::class, $this->repository);

        $subscriber = $this->app->make(JobLifecycleSubscriber::class);
        $subscriber->handleJobProcessing(
            new JobProcessing('database', $this->makeJob(uuid: self::UUID)),
        );

        $stored = $this->repository->findByIdentifierAndAttempt(
            new JobIdentifier(self::UUID),
            Attempt::first(),
        );

        self::assertNotNull($stored);
        self::assertSame('database', $stored->connection);
    }

    private function makeJob(string $uuid, int $attempts = 1): Job
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('uuid')->andReturn($uuid);
        $job->shouldReceive('attempts')->andReturn($attempts);
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\SendInvoice');
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('payload')->andReturn([
            'displayName' => 'App\\Jobs\\SendInvoice',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['command' => 'serialized'],
        ]);

        return $job;
    }
}
