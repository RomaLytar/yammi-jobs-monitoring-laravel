<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Progress;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Application\Action\CaptureOutcomeReportAction;
use Yammi\JobsMonitor\Application\Action\RecordJobProgressAction;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Enum\OutcomeStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport;
use Yammi\JobsMonitor\Tests\TestCase;

final class ProgressAndOutcomeTest extends TestCase
{
    public function test_progress_is_persisted_and_partial_completion_counted(): void
    {
        $this->seedRecord('11111111-1111-1111-1111-111111111111', JobStatus::Failed);

        $action = $this->app->make(RecordJobProgressAction::class);
        $action(uuid: '11111111-1111-1111-1111-111111111111', attempt: 1, current: 500, total: 1000, description: 'processing rows');

        $row = DB::table('jobs_monitor')->where('uuid', '11111111-1111-1111-1111-111111111111')->first();
        self::assertSame(500, (int) $row->progress_current);
        self::assertSame(1000, (int) $row->progress_total);
        self::assertSame('processing rows', $row->progress_description);

        /** @var JobRecordRepository $repo */
        $repo = $this->app->make(JobRecordRepository::class);
        self::assertSame(1, $repo->countPartialCompletionsSince(new DateTimeImmutable('-1 hour')));
    }

    public function test_outcome_report_is_persisted_and_zero_processed_counted(): void
    {
        $this->seedRecord('22222222-2222-2222-2222-222222222222', JobStatus::Processed);

        $action = $this->app->make(CaptureOutcomeReportAction::class);
        $action(uuid: '22222222-2222-2222-2222-222222222222', attempt: 1, report: new OutcomeReport(
            processed: 0,
            skipped: 10,
            warnings: ['empty source'],
            status: OutcomeStatus::NoOp,
        ));

        $row = DB::table('jobs_monitor')->where('uuid', '22222222-2222-2222-2222-222222222222')->first();
        self::assertSame(0, (int) $row->outcome_processed);
        self::assertSame(10, (int) $row->outcome_skipped);
        self::assertSame(OutcomeStatus::NoOp->value, $row->outcome_status);

        /** @var JobRecordRepository $repo */
        $repo = $this->app->make(JobRecordRepository::class);
        self::assertSame(1, $repo->countZeroProcessedSince(new DateTimeImmutable('-1 hour')));
    }

    public function test_outcome_with_positive_processed_does_not_count_as_zero_processed(): void
    {
        $this->seedRecord('33333333-3333-3333-3333-333333333333', JobStatus::Processed);

        $this->app->make(CaptureOutcomeReportAction::class)(
            uuid: '33333333-3333-3333-3333-333333333333',
            attempt: 1,
            report: new OutcomeReport(processed: 42, skipped: 0, warnings: [], status: OutcomeStatus::Ok),
        );

        /** @var JobRecordRepository $repo */
        $repo = $this->app->make(JobRecordRepository::class);
        self::assertSame(0, $repo->countZeroProcessedSince(new DateTimeImmutable('-1 hour')));
    }

    public function test_partial_counter_ignores_successful_runs_even_with_progress(): void
    {
        $this->seedRecord('44444444-4444-4444-4444-444444444444', JobStatus::Processed);
        $this->app->make(RecordJobProgressAction::class)(uuid: '44444444-4444-4444-4444-444444444444', attempt: 1, current: 10, total: 10);

        /** @var JobRecordRepository $repo */
        $repo = $this->app->make(JobRecordRepository::class);
        self::assertSame(0, $repo->countPartialCompletionsSince(new DateTimeImmutable('-1 hour')));
    }

    private function seedRecord(string $uuid, JobStatus $status): void
    {
        $now = new DateTimeImmutable;
        DB::table('jobs_monitor')->insert([
            'uuid' => $uuid,
            'attempt' => 1,
            'job_class' => 'App\\Jobs\\Test',
            'connection' => 'sync',
            'queue' => 'default',
            'status' => $status->value,
            'started_at' => $now->format('Y-m-d H:i:s.u'),
            'finished_at' => $status === JobStatus::Processing ? null : $now->format('Y-m-d H:i:s.u'),
            'duration_ms' => 100,
        ]);
    }
}
