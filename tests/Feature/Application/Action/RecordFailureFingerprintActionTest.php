<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Application\Action;

use DateTimeImmutable;
use RuntimeException;
use Yammi\JobsMonitor\Application\Action\RecordFailureFingerprintAction;
use Yammi\JobsMonitor\Application\Action\StoreJobRecordAction;
use Yammi\JobsMonitor\Application\DTO\JobRecordData;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;
use Yammi\JobsMonitor\Tests\TestCase;

final class RecordFailureFingerprintActionTest extends TestCase
{
    private const UUID_A = '550e8400-e29b-41d4-a716-446655440000';

    private const UUID_B = '11111111-2222-3333-4444-555555555555';

    private RecordFailureFingerprintAction $action;

    private FailureGroupRepository $groups;

    private JobRecordRepository $jobs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = $this->app->make(RecordFailureFingerprintAction::class);
        $this->groups = $this->app->make(FailureGroupRepository::class);
        $this->jobs = $this->app->make(JobRecordRepository::class);
    }

    public function test_first_occurrence_creates_a_new_group(): void
    {
        $this->seedFailedAttempt(self::UUID_A, attempt: 1, jobClass: 'App\\Jobs\\OrderJob');

        $exception = $this->makeException('Boom');

        $fingerprint = ($this->action)(
            id: self::UUID_A,
            attempt: 1,
            jobClass: 'App\\Jobs\\OrderJob',
            exception: $exception,
            occurredAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        );

        $group = $this->groups->findByFingerprint($fingerprint);

        self::assertNotNull($group);
        self::assertSame(1, $group->occurrences());
        self::assertSame(['App\\Jobs\\OrderJob'], $group->affectedJobClasses());
        self::assertSame(RuntimeException::class, $group->sampleExceptionClass());
        self::assertSame('Boom', $group->sampleMessage());
    }

    public function test_repeat_occurrence_increments_count_on_existing_group(): void
    {
        $this->seedFailedAttempt(self::UUID_A, 1, 'App\\Jobs\\OrderJob');
        $this->seedFailedAttempt(self::UUID_B, 1, 'App\\Jobs\\OrderSyncJob');

        $exception = $this->makeException('Boom');

        ($this->action)(
            id: self::UUID_A,
            attempt: 1,
            jobClass: 'App\\Jobs\\OrderJob',
            exception: $exception,
            occurredAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        );

        $fingerprint = ($this->action)(
            id: self::UUID_B,
            attempt: 1,
            jobClass: 'App\\Jobs\\OrderSyncJob',
            exception: $exception,
            occurredAt: new DateTimeImmutable('2026-01-01 12:05:00'),
        );

        $group = $this->groups->findByFingerprint($fingerprint);

        self::assertNotNull($group);
        self::assertSame(2, $group->occurrences());
        self::assertSame(
            ['App\\Jobs\\OrderJob', 'App\\Jobs\\OrderSyncJob'],
            $group->affectedJobClasses(),
        );
        self::assertTrue($group->lastJobId()->equals(new JobIdentifier(self::UUID_B)));
        self::assertSame(1, $this->groups->countAll());
    }

    public function test_backfills_failure_fingerprint_on_the_job_record(): void
    {
        $this->seedFailedAttempt(self::UUID_A, 1, 'App\\Jobs\\OrderJob');

        $fingerprint = ($this->action)(
            id: self::UUID_A,
            attempt: 1,
            jobClass: 'App\\Jobs\\OrderJob',
            exception: $this->makeException('Boom'),
            occurredAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        );

        /** @var JobRecordModel|null $model */
        $model = JobRecordModel::query()
            ->where('uuid', self::UUID_A)
            ->where('attempt', 1)
            ->first();

        self::assertNotNull($model);
        self::assertSame($fingerprint->hash, $model->failure_fingerprint);
    }

    public function test_same_exception_on_different_uuids_yields_same_fingerprint(): void
    {
        $exception = $this->makeException('Boom');

        $this->seedFailedAttempt(self::UUID_A, 1, 'App\\Jobs\\OrderJob');
        $this->seedFailedAttempt(self::UUID_B, 1, 'App\\Jobs\\OrderJob');

        $a = ($this->action)(
            id: self::UUID_A, attempt: 1, jobClass: 'App\\Jobs\\OrderJob',
            exception: $exception, occurredAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        );
        $b = ($this->action)(
            id: self::UUID_B, attempt: 1, jobClass: 'App\\Jobs\\OrderJob',
            exception: $exception, occurredAt: new DateTimeImmutable('2026-01-01 12:05:00'),
        );

        self::assertTrue($a->equals($b));
    }

    public function test_different_exception_classes_yield_different_fingerprints(): void
    {
        $this->seedFailedAttempt(self::UUID_A, 1, 'App\\Jobs\\OrderJob');
        $this->seedFailedAttempt(self::UUID_B, 1, 'App\\Jobs\\OrderJob');

        $a = ($this->action)(
            id: self::UUID_A, attempt: 1, jobClass: 'App\\Jobs\\OrderJob',
            exception: new RuntimeException('same message'),
            occurredAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        );
        $b = ($this->action)(
            id: self::UUID_B, attempt: 1, jobClass: 'App\\Jobs\\OrderJob',
            exception: new \LogicException('same message'),
            occurredAt: new DateTimeImmutable('2026-01-01 12:05:00'),
        );

        self::assertFalse($a->equals($b));
        self::assertSame(2, $this->groups->countAll());
    }

    private function seedFailedAttempt(string $uuid, int $attempt, string $jobClass): void
    {
        $store = $this->app->make(StoreJobRecordAction::class);
        $now = new DateTimeImmutable('2026-01-01 11:59:00');

        $store(new JobRecordData(
            id: $uuid,
            attempt: $attempt,
            jobClass: $jobClass,
            connection: 'redis',
            queue: 'default',
            status: JobStatus::Failed,
            startedAt: $now,
            finishedAt: $now,
            exception: 'RuntimeException: Boom',
        ));
    }

    private function makeException(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }
}
