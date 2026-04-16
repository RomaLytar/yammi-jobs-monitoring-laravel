<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use Mockery;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\RecordJobProgressAction;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobProgress;

final class RecordJobProgressActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_records_progress_with_all_fields(): void
    {
        $repo = Mockery::mock(JobRecordRepository::class);
        $repo->shouldReceive('recordProgress')
            ->once()
            ->withArgs(function (JobIdentifier $id, Attempt $attempt, JobProgress $progress): bool {
                return $id->value === '550e8400-e29b-41d4-a716-446655440001'
                    && $attempt->value === 1
                    && $progress->current === 50
                    && $progress->total === 100
                    && $progress->description === 'Halfway there'
                    && $progress->updatedAt !== null;
            });

        $action = new RecordJobProgressAction($repo);

        $action(
            uuid: '550e8400-e29b-41d4-a716-446655440001',
            attempt: 1,
            current: 50,
            total: 100,
            description: 'Halfway there',
        );

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_records_progress_without_optional_fields(): void
    {
        $repo = Mockery::mock(JobRecordRepository::class);
        $repo->shouldReceive('recordProgress')
            ->once()
            ->withArgs(function (JobIdentifier $id, Attempt $attempt, JobProgress $progress): bool {
                return $id->value === '550e8400-e29b-41d4-a716-446655440002'
                    && $attempt->value === 2
                    && $progress->current === 10
                    && $progress->total === null
                    && $progress->description === null;
            });

        $action = new RecordJobProgressAction($repo);

        $action(
            uuid: '550e8400-e29b-41d4-a716-446655440002',
            attempt: 2,
            current: 10,
        );

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_constructs_correct_value_objects_for_repository(): void
    {
        $capturedId = null;
        $capturedAttempt = null;

        $repo = Mockery::mock(JobRecordRepository::class);
        $repo->shouldReceive('recordProgress')
            ->once()
            ->withArgs(function (JobIdentifier $id, Attempt $attempt, JobProgress $progress) use (&$capturedId, &$capturedAttempt): bool {
                $capturedId = $id;
                $capturedAttempt = $attempt;

                return true;
            });

        $action = new RecordJobProgressAction($repo);

        $action(
            uuid: '550e8400-e29b-41d4-a716-446655440003',
            attempt: 3,
            current: 0,
            total: 50,
            description: 'Starting',
        );

        self::assertInstanceOf(JobIdentifier::class, $capturedId);
        self::assertSame('550e8400-e29b-41d4-a716-446655440003', $capturedId->value);
        self::assertInstanceOf(Attempt::class, $capturedAttempt);
        self::assertSame(3, $capturedAttempt->value);
    }
}
