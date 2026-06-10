<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Console;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Enum\DurationAnomalyKind;
use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationAnomaly;
use Yammi\JobsMonitor\Domain\Job\ValueObject\DurationBaseline;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationAnomalyModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationBaselineModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\FailureGroupModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\ScheduledTaskRunModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\WorkerHeartbeatModel;
use Yammi\JobsMonitor\Tests\TestCase;

final class PruneMonitorDataCommandTest extends TestCase
{
    public function test_prunes_every_historical_table_by_retention_and_keeps_baselines(): void
    {
        config()->set('jobs-monitor.retention_days', 180);
        config()->set('jobs-monitor.workers.retention_days', 30);

        $this->seedAll();

        $this->artisan('jobs-monitor:prune')
            ->expectsOutputToContain('Pruned 5 rows across 5 datasets.')
            ->assertSuccessful();

        // One old row removed per historical table; the recent row survives.
        self::assertSame(1, JobRecordModel::query()->count());
        self::assertSame(1, FailureGroupModel::query()->count());
        self::assertSame(1, ScheduledTaskRunModel::query()->count());
        self::assertSame(1, DurationAnomalyModel::query()->count());
        self::assertSame(1, WorkerHeartbeatModel::query()->count());

        // Baselines are derived state and must never be pruned.
        self::assertSame(1, DurationBaselineModel::query()->count());
    }

    public function test_heartbeats_use_their_own_shorter_retention(): void
    {
        config()->set('jobs-monitor.retention_days', 180);
        config()->set('jobs-monitor.workers.retention_days', 30);

        // 40 days old: survives the 180-day main retention but not the 30-day worker one.
        $this->seedHeartbeat('w-old', now()->subDays(40));
        $this->seedHeartbeat('w-new', now()->subDays(5));

        $this->artisan('jobs-monitor:prune')->assertSuccessful();

        self::assertSame(1, WorkerHeartbeatModel::query()->count());
    }

    public function test_days_option_overrides_main_retention_only(): void
    {
        config()->set('jobs-monitor.retention_days', 180);

        // 50 days old: kept under the default 180, removed when --days=30 is given.
        $this->seedJob('550e8400-e29b-41d4-a716-446655449999', now()->subDays(50));

        $this->artisan('jobs-monitor:prune', ['--days' => 30])->assertSuccessful();

        self::assertSame(0, JobRecordModel::query()->count());
    }

    private function seedAll(): void
    {
        $this->seedJob('550e8400-e29b-41d4-a716-446655440001', now()->subDays(200));
        $this->seedJob('550e8400-e29b-41d4-a716-446655440002', now()->subDays(10));

        /** @var FailureGroupRepository $failures */
        $failures = $this->app->make(FailureGroupRepository::class);
        $failures->save($this->failureGroup('0123456789abcde1', new DateTimeImmutable('-200 days')));
        $failures->save($this->failureGroup('0123456789abcde2', new DateTimeImmutable('-10 days')));

        /** @var ScheduledTaskRunRepository $scheduled */
        $scheduled = $this->app->make(ScheduledTaskRunRepository::class);
        $scheduled->save(new ScheduledTaskRun(mutex: 'old-task', taskName: 'OldTask', expression: '* * * * *', timezone: 'UTC', startedAt: new DateTimeImmutable('-200 days')));
        $scheduled->save(new ScheduledTaskRun(mutex: 'new-task', taskName: 'NewTask', expression: '* * * * *', timezone: 'UTC', startedAt: new DateTimeImmutable('-10 days')));

        /** @var DurationBaselineRepository $durations */
        $durations = $this->app->make(DurationBaselineRepository::class);
        $durations->recordAnomaly($this->anomaly('550e8400-e29b-41d4-a716-446655440003', new DateTimeImmutable('-200 days')));
        $durations->recordAnomaly($this->anomaly('550e8400-e29b-41d4-a716-446655440004', new DateTimeImmutable('-10 days')));
        $durations->saveBaseline(new DurationBaseline('App\\Jobs\\X', 100, 10, 20, 5, 50, new DateTimeImmutable('-30 days'), new DateTimeImmutable));

        $this->seedHeartbeat('worker-old', now()->subDays(200));
        $this->seedHeartbeat('worker-new', now()->subDays(5));
    }

    private function seedJob(string $uuid, \DateTimeInterface $startedAt): void
    {
        JobRecordModel::query()->create([
            'uuid' => $uuid,
            'attempt' => 1,
            'job_class' => 'App\\Jobs\\TestJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'started_at' => $startedAt,
            'finished_at' => $startedAt,
            'duration_ms' => 100,
        ]);
    }

    private function failureGroup(string $fingerprint, DateTimeImmutable $lastSeenAt): FailureGroup
    {
        return new FailureGroup(
            new FailureFingerprint($fingerprint),
            $lastSeenAt->modify('-1 hour'),
            $lastSeenAt,
            3,
            ['App\\Jobs\\TestJob'],
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440099'),
            'RuntimeException',
            'boom',
            '#0 stack',
        );
    }

    private function anomaly(string $uuid, DateTimeImmutable $detectedAt): DurationAnomaly
    {
        return new DurationAnomaly(
            jobUuid: $uuid,
            attempt: 1,
            jobClass: 'App\\Jobs\\TestJob',
            kind: DurationAnomalyKind::Long,
            durationMs: 9000,
            baselineP50Ms: 100,
            baselineP95Ms: 200,
            samplesCount: 50,
            detectedAt: $detectedAt,
        );
    }

    private function seedHeartbeat(string $workerId, \DateTimeInterface $lastSeenAt): void
    {
        /** @var WorkerRepository $workers */
        $workers = $this->app->make(WorkerRepository::class);
        $workers->recordHeartbeat(new WorkerHeartbeat(
            workerId: new WorkerIdentifier($workerId),
            connection: 'redis',
            queue: 'default',
            host: 'host-1',
            pid: 1234,
            lastSeenAt: DateTimeImmutable::createFromInterface($lastSeenAt),
        ));
    }
}
