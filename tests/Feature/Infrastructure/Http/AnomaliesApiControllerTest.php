<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Tests\TestCase;

final class AnomaliesApiControllerTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function enableApi($app): void
    {
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    /**
     * @define-env enableApi
     */
    public function test_baselines_endpoint_lists_baselines(): void
    {
        $this->seedBaseline('App\\Jobs\\Foo', samples: 50, p50: 800, p95: 2400);
        $this->seedBaseline('App\\Jobs\\Bar', samples: 10, p50: 100, p95: 300);

        $response = $this->getJson('/api/jobs-monitor/anomalies/baselines');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['job_class', 'samples_count', 'p50_ms', 'p95_ms', 'min_ms', 'max_ms', 'computed_over_from', 'computed_over_to']],
            'meta' => ['total', 'page', 'per_page', 'last_page'],
        ]);
        self::assertSame(2, $response->json('meta.total'));
    }

    /**
     * @define-env enableApi
     */
    public function test_anomalies_endpoint_filters_by_kind(): void
    {
        $this->seedAnomaly('App\\Jobs\\Foo', 'short', durationMs: 50);
        $this->seedAnomaly('App\\Jobs\\Foo', 'long', durationMs: 5000);

        $response = $this->getJson('/api/jobs-monitor/anomalies?kind=long');

        $response->assertOk();
        self::assertSame(1, $response->json('meta.total'));
        self::assertSame('long', $response->json('data.0.kind'));
        // Aggregate counters always reflect the full set, not the filter.
        self::assertSame(1, $response->json('meta.short_count'));
        self::assertSame(1, $response->json('meta.long_count'));
    }

    /**
     * @define-env enableApi
     */
    public function test_silent_successes_picks_up_no_op_processed_zero_and_warnings(): void
    {
        $this->seedJob(JobStatus::Processed, outcomeStatus: 'no_op', processed: 0);
        $this->seedJob(JobStatus::Processed, outcomeStatus: 'ok', processed: 0); // processed=0 → suspicious
        $this->seedJob(JobStatus::Processed, outcomeStatus: 'ok', processed: 10, warnings: 2);
        $this->seedJob(JobStatus::Processed, outcomeStatus: 'ok', processed: 10, warnings: 0); // healthy → excluded

        $response = $this->getJson('/api/jobs-monitor/anomalies/silent-successes');

        $response->assertOk();
        self::assertSame(3, $response->json('meta.total'));
    }

    /**
     * @define-env enableApi
     */
    public function test_partial_completions_only_returns_failed_with_progress(): void
    {
        // Match: failed mid-progress.
        $this->seedJob(JobStatus::Failed, progressCurrent: 87, progressTotal: 200);
        // Excluded: failed but no progress.
        $this->seedJob(JobStatus::Failed);
        // Excluded: completed cleanly with progress.
        $this->seedJob(JobStatus::Processed, progressCurrent: 200, progressTotal: 200);

        $response = $this->getJson('/api/jobs-monitor/anomalies/partial-completions');

        $response->assertOk();
        self::assertSame(1, $response->json('meta.total'));
        self::assertSame(43.5, $response->json('data.0.progress_percentage'));
    }

    /**
     * @define-env enableApi
     */
    public function test_refresh_baselines_endpoint_runs_action_and_reports_count(): void
    {
        // Seed enough successful runs so the refresh action actually has
        // something to compute (the action skips classes with <= 3 samples).
        for ($i = 0; $i < 10; $i++) {
            $this->seedJob(JobStatus::Processed, processed: $i, durationMs: 100 + $i * 10);
        }

        $response = $this->postJson('/api/jobs-monitor/anomalies/refresh-baselines', ['lookback_days' => 7]);

        $response->assertOk();
        $response->assertJsonStructure(['updated', 'lookback_days']);
        self::assertGreaterThanOrEqual(1, $response->json('updated'));
        self::assertSame(7, $response->json('lookback_days'));
    }

    private function seedBaseline(string $jobClass, int $samples, int $p50, int $p95): void
    {
        DB::table('jobs_monitor_duration_baselines')->insert([
            'job_class' => $jobClass,
            'samples_count' => $samples,
            'p50_ms' => $p50,
            'p95_ms' => $p95,
            'min_ms' => max(10, $p50 - 50),
            'max_ms' => $p95 + 100,
            'computed_over_from' => now()->subDays(7),
            'computed_over_to' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAnomaly(string $jobClass, string $kind, int $durationMs): void
    {
        DB::table('jobs_monitor_duration_anomalies')->insert([
            'job_uuid' => Str::uuid()->toString(),
            'attempt' => 1,
            'job_class' => $jobClass,
            'kind' => $kind,
            'duration_ms' => $durationMs,
            'baseline_p50_ms' => 800,
            'baseline_p95_ms' => 2400,
            'samples_count' => 100,
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedJob(
        JobStatus $status,
        ?string $outcomeStatus = null,
        ?int $processed = null,
        int $warnings = 0,
        ?int $progressCurrent = null,
        ?int $progressTotal = null,
        int $durationMs = 100,
    ): void {
        $now = (new DateTimeImmutable)->format('Y-m-d H:i:s.u');

        DB::table('jobs_monitor')->insert([
            'uuid' => Str::uuid()->toString(),
            'attempt' => 1,
            'job_class' => 'App\\Jobs\\Test',
            'connection' => 'sync',
            'queue' => 'default',
            'status' => $status->value,
            'started_at' => $now,
            'finished_at' => $now,
            'duration_ms' => $durationMs,
            'outcome_status' => $outcomeStatus,
            'outcome_processed' => $processed,
            'outcome_skipped' => 0,
            'outcome_warnings_count' => $warnings,
            'progress_current' => $progressCurrent,
            'progress_total' => $progressTotal,
            'progress_description' => $progressCurrent !== null ? 'rows' : null,
            'progress_updated_at' => $progressCurrent !== null ? $now : null,
            'exception' => $status === JobStatus::Failed ? 'RuntimeException: boom' : null,
        ]);
    }
}
