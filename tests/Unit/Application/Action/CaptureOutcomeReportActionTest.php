<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use Mockery;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\CaptureOutcomeReportAction;
use Yammi\JobsMonitor\Domain\Job\Enum\OutcomeStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport;

final class CaptureOutcomeReportActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delegates_to_repository_with_correct_value_objects(): void
    {
        $report = new OutcomeReport(
            processed: 42,
            skipped: 3,
            warnings: ['Slow API response'],
            status: OutcomeStatus::Ok,
        );

        $repo = Mockery::mock(JobRecordRepository::class);
        $repo->shouldReceive('recordOutcome')
            ->once()
            ->withArgs(function (JobIdentifier $id, Attempt $attempt, OutcomeReport $outcome) use ($report): bool {
                return $id->value === '550e8400-e29b-41d4-a716-446655440001'
                    && $attempt->value === 1
                    && $outcome === $report;
            });

        $action = new CaptureOutcomeReportAction($repo);

        $action(
            uuid: '550e8400-e29b-41d4-a716-446655440001',
            attempt: 1,
            report: $report,
        );

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_captures_noop_outcome(): void
    {
        $report = new OutcomeReport(
            processed: 0,
            skipped: 0,
            warnings: [],
            status: OutcomeStatus::NoOp,
        );

        $repo = Mockery::mock(JobRecordRepository::class);
        $repo->shouldReceive('recordOutcome')
            ->once()
            ->withArgs(function (JobIdentifier $id, Attempt $attempt, OutcomeReport $outcome): bool {
                return $outcome->processed === 0
                    && $outcome->status === OutcomeStatus::NoOp;
            });

        $action = new CaptureOutcomeReportAction($repo);

        $action(
            uuid: '550e8400-e29b-41d4-a716-446655440002',
            attempt: 1,
            report: $report,
        );

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_captures_degraded_outcome_with_warnings(): void
    {
        $report = new OutcomeReport(
            processed: 10,
            skipped: 5,
            warnings: ['Timeout on batch 3', 'Retried batch 7'],
            status: OutcomeStatus::Degraded,
        );

        $repo = Mockery::mock(JobRecordRepository::class);
        $repo->shouldReceive('recordOutcome')
            ->once()
            ->withArgs(function (JobIdentifier $id, Attempt $attempt, OutcomeReport $outcome): bool {
                return $id->value === '550e8400-e29b-41d4-a716-446655440003'
                    && $attempt->value === 2
                    && $outcome->processed === 10
                    && $outcome->skipped === 5
                    && count($outcome->warnings) === 2
                    && $outcome->status === OutcomeStatus::Degraded;
            });

        $action = new CaptureOutcomeReportAction($repo);

        $action(
            uuid: '550e8400-e29b-41d4-a716-446655440003',
            attempt: 2,
            report: $report,
        );

        Mockery::close();
        self::assertTrue(true);
    }

    public function test_constructs_job_identifier_and_attempt_correctly(): void
    {
        $capturedId = null;
        $capturedAttempt = null;

        $report = new OutcomeReport(
            processed: 1,
            skipped: 0,
            warnings: [],
            status: OutcomeStatus::Ok,
        );

        $repo = Mockery::mock(JobRecordRepository::class);
        $repo->shouldReceive('recordOutcome')
            ->once()
            ->withArgs(function (JobIdentifier $id, Attempt $attempt) use (&$capturedId, &$capturedAttempt): bool {
                $capturedId = $id;
                $capturedAttempt = $attempt;

                return true;
            });

        $action = new CaptureOutcomeReportAction($repo);

        $action(
            uuid: '550e8400-e29b-41d4-a716-446655440004',
            attempt: 5,
            report: $report,
        );

        self::assertInstanceOf(JobIdentifier::class, $capturedId);
        self::assertSame('550e8400-e29b-41d4-a716-446655440004', $capturedId->value);
        self::assertInstanceOf(Attempt::class, $capturedAttempt);
        self::assertSame(5, $capturedAttempt->value);
    }
}
