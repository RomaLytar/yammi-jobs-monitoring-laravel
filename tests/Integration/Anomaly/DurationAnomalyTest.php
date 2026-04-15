<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Anomaly;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Application\Action\DetectDurationAnomalyAction;
use Yammi\JobsMonitor\Application\Action\RefreshDurationBaselinesAction;
use Yammi\JobsMonitor\Domain\Job\Enum\DurationAnomalyKind;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Tests\TestCase;

final class DurationAnomalyTest extends TestCase
{
    public function test_refresh_creates_baselines_from_successful_runs(): void
    {
        $class = 'App\\Jobs\\ProcessImport';
        $this->seedSuccessfulRuns($class, array_fill(0, 50, 1000));

        $action = $this->app->make(RefreshDurationBaselinesAction::class);
        $updated = $action(new DateTimeImmutable);

        self::assertSame(1, $updated);

        $row = DB::table('jobs_monitor_duration_baselines')->where('job_class', $class)->first();
        self::assertNotNull($row);
        self::assertSame(50, (int) $row->samples_count);
        self::assertSame(1000, (int) $row->p50_ms);
    }

    public function test_detects_short_anomaly_when_run_is_much_faster_than_baseline(): void
    {
        $class = 'App\\Jobs\\ProcessImport';
        $this->seedSuccessfulRuns($class, array_fill(0, 50, 1000));
        $this->app->make(RefreshDurationBaselinesAction::class)(new DateTimeImmutable);

        /** @var DetectDurationAnomalyAction $detector */
        $detector = $this->app->make(DetectDurationAnomalyAction::class);

        $result = $detector(
            jobUuid: 'test-uuid-1',
            attempt: 1,
            jobClass: $class,
            durationMs: 50, // p50 was 1000, short_factor default 0.1 → threshold 100ms
            detectedAt: new DateTimeImmutable,
        );

        self::assertNotNull($result);
        self::assertSame(DurationAnomalyKind::Short, $result->kind);
    }

    public function test_detects_long_anomaly_when_run_is_much_slower_than_baseline(): void
    {
        $class = 'App\\Jobs\\ProcessImport';
        $this->seedSuccessfulRuns($class, array_fill(0, 50, 1000));
        $this->app->make(RefreshDurationBaselinesAction::class)(new DateTimeImmutable);

        /** @var DetectDurationAnomalyAction $detector */
        $detector = $this->app->make(DetectDurationAnomalyAction::class);

        $result = $detector(
            jobUuid: 'test-uuid-2',
            attempt: 1,
            jobClass: $class,
            durationMs: 10_000, // p95 was 1000, long_factor default 3.0 → threshold 3000ms
            detectedAt: new DateTimeImmutable,
        );

        self::assertNotNull($result);
        self::assertSame(DurationAnomalyKind::Long, $result->kind);
    }

    public function test_no_anomaly_when_baseline_has_too_few_samples(): void
    {
        $class = 'App\\Jobs\\YoungJob';
        $this->seedSuccessfulRuns($class, array_fill(0, 5, 1000));
        $this->app->make(RefreshDurationBaselinesAction::class)(new DateTimeImmutable);

        $result = $this->app->make(DetectDurationAnomalyAction::class)(
            jobUuid: 'young-uuid',
            attempt: 1,
            jobClass: $class,
            durationMs: 1, // would be an anomaly if we had enough samples
            detectedAt: new DateTimeImmutable,
        );

        self::assertNull($result);
    }

    public function test_healthy_run_does_not_record_anomaly(): void
    {
        $class = 'App\\Jobs\\ProcessImport';
        $this->seedSuccessfulRuns($class, array_fill(0, 50, 1000));
        $this->app->make(RefreshDurationBaselinesAction::class)(new DateTimeImmutable);

        $result = $this->app->make(DetectDurationAnomalyAction::class)(
            jobUuid: 'healthy-uuid',
            attempt: 1,
            jobClass: $class,
            durationMs: 1100,
            detectedAt: new DateTimeImmutable,
        );

        self::assertNull($result);
        self::assertSame(0, DB::table('jobs_monitor_duration_anomalies')->count());
    }

    /**
     * @param  list<int>  $durationsMs
     */
    private function seedSuccessfulRuns(string $jobClass, array $durationsMs): void
    {
        $now = new DateTimeImmutable;
        $rows = [];
        foreach ($durationsMs as $i => $durationMs) {
            $rows[] = [
                'uuid' => 'seed-'.$jobClass.'-'.$i,
                'attempt' => 1,
                'job_class' => $jobClass,
                'connection' => 'sync',
                'queue' => 'default',
                'status' => JobStatus::Processed->value,
                'started_at' => $now->format('Y-m-d H:i:s.u'),
                'finished_at' => $now->format('Y-m-d H:i:s.u'),
                'duration_ms' => $durationMs,
            ];
        }

        DB::table('jobs_monitor')->insert($rows);
    }
}
