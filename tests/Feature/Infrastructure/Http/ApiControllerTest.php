<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\TestCase;

final class ApiControllerTest extends TestCase
{
    /**
     * @define-env enableApi
     */
    public function test_recent_jobs_returns_json(): void
    {
        $response = $this->getJson('/api/jobs-monitor/jobs');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    /**
     * @define-env enableApi
     */
    public function test_recent_jobs_returns_job_records(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440000'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:02Z'));
        $repository->save($record);

        $response = $this->getJson('/api/jobs-monitor/jobs?period=all');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'job_class' => 'App\\Jobs\\SendInvoice',
            'status' => 'processed',
            'queue' => 'default',
        ]);
    }

    /**
     * @define-env enableApi
     */
    public function test_recent_failures_returns_only_failed(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $now = new DateTimeImmutable;

        $processed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-5 minutes'),
        );
        $processed->markAsProcessed($now->modify('-4 minutes'));

        $failed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\ProcessPayment',
            connection: 'redis',
            queue: new QueueName('payments'),
            startedAt: $now->modify('-2 minutes'),
        );
        $failed->markAsFailed($now->modify('-1 minute'), 'RuntimeException: Timeout');

        $repository->save($processed);
        $repository->save($failed);

        $response = $this->getJson('/api/jobs-monitor/failures');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'status' => 'failed',
            'job_class' => 'App\\Jobs\\ProcessPayment',
        ]);
    }

    /**
     * @define-env enableApi
     */
    public function test_stats_returns_aggregate_data(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440003'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:02Z'));
        $repository->save($record);

        $response = $this->getJson('/api/jobs-monitor/stats?'.http_build_query([
            'job_class' => 'App\\Jobs\\SendInvoice',
            'period' => 'all',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'total' => 1,
            'processed' => 1,
            'failed' => 0,
        ]);
    }

    /**
     * @define-env enableApi
     */
    public function test_stats_requires_job_class_parameter(): void
    {
        $response = $this->getJson('/api/jobs-monitor/stats');

        $response->assertStatus(422);
    }

    public function test_api_returns_404_when_disabled(): void
    {
        $response = $this->getJson('/api/jobs-monitor/jobs');

        $response->assertNotFound();
    }

    /**
     * @define-env useCustomApiPath
     */
    public function test_api_respects_custom_path(): void
    {
        $response = $this->getJson('/api/v2/monitor/jobs');

        $response->assertOk();
    }

    /**
     * @define-env enableApi
     */
    public function test_jobs_endpoint_returns_failure_category_in_payload(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(
            new DateTimeImmutable('2026-01-01T00:00:01Z'),
            'RuntimeException: connection refused',
            FailureCategory::Transient,
        );
        $repository->save($record);

        $response = $this->getJson('/api/jobs-monitor/jobs?period=all');

        $response->assertOk();
        $response->assertJsonFragment(['failure_category' => 'transient']);
    }

    /**
     * @define-env enableApi
     */
    public function test_jobs_endpoint_filters_by_queue(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        foreach ([['550e8400-e29b-41d4-a716-446655440001', 'default'], ['550e8400-e29b-41d4-a716-446655440002', 'emails']] as [$uuid, $queue]) {
            $repository->save(new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: 'redis',
                queue: new QueueName($queue),
                startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            ));
        }

        $response = $this->getJson('/api/jobs-monitor/jobs?period=all&queue=emails');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['queue' => 'emails']);
    }

    /**
     * @define-env enableApi
     */
    public function test_jobs_endpoint_filters_by_connection(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        foreach ([['550e8400-e29b-41d4-a716-446655440001', 'redis'], ['550e8400-e29b-41d4-a716-446655440002', 'sqs']] as [$uuid, $connection]) {
            $repository->save(new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: $connection,
                queue: new QueueName('default'),
                startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            ));
        }

        $response = $this->getJson('/api/jobs-monitor/jobs?period=all&connection=sqs');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['connection' => 'sqs']);
    }

    /**
     * @define-env enableApi
     */
    public function test_jobs_endpoint_filters_by_failure_category(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $transient = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\A',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $transient->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'timeout', FailureCategory::Transient);

        $permanent = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\B',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $permanent->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'validation', FailureCategory::Permanent);

        $repository->save($transient);
        $repository->save($permanent);

        $response = $this->getJson('/api/jobs-monitor/jobs?period=all&failure_category=permanent');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['failure_category' => 'permanent']);
    }

    /**
     * @define-env enableApi
     */
    public function test_jobs_endpoint_filters_by_status(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $processed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\A',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $processed->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));

        $failed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\B',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $failed->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom');

        $repository->save($processed);
        $repository->save($failed);

        $response = $this->getJson('/api/jobs-monitor/jobs?period=all&status=failed');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['status' => 'failed']);
    }

    /**
     * @define-env enableApi
     */
    public function test_failures_endpoint_honors_queue_and_category_filters(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $defaultTransient = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\A',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $defaultTransient->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'timeout', FailureCategory::Transient);

        $emailsPermanent = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\B',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $emailsPermanent->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'validation', FailureCategory::Permanent);

        $repository->save($defaultTransient);
        $repository->save($emailsPermanent);

        $response = $this->getJson('/api/jobs-monitor/failures?period=all&queue=emails&failure_category=permanent');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['queue' => 'emails', 'failure_category' => 'permanent']);
    }

    /**
     * @define-env enableApi
     */
    public function test_attempts_endpoint_returns_all_attempts_for_a_uuid(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        foreach ([1, 2, 3] as $i) {
            $record = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: new Attempt($i),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: new DateTimeImmutable("2026-01-01T00:00:{$i}0Z"),
            );
            if ($i < 3) {
                $record->markAsFailed(new DateTimeImmutable("2026-01-01T00:00:{$i}1Z"), "failure {$i}");
            } else {
                $record->markAsProcessed(new DateTimeImmutable("2026-01-01T00:00:{$i}1Z"));
            }
            $repository->save($record);
        }

        $response = $this->getJson("/api/jobs-monitor/jobs/{$uuid}/attempts");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.attempt', 1);
        $response->assertJsonPath('data.1.attempt', 2);
        $response->assertJsonPath('data.2.attempt', 3);
        $response->assertJsonPath('data.2.status', 'processed');
    }

    /**
     * @define-env enableApi
     */
    public function test_attempts_endpoint_returns_empty_for_unknown_uuid(): void
    {
        $response = $this->getJson('/api/jobs-monitor/jobs/550e8400-e29b-41d4-a716-446655440099/attempts');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    /**
     * @define-env enableApi
     */
    public function test_stats_overview_endpoint_returns_aggregated_data(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        foreach ([
            ['550e8400-e29b-41d4-a716-446655440001', 'App\\Jobs\\SendInvoice'],
            ['550e8400-e29b-41d4-a716-446655440002', 'App\\Jobs\\SendInvoice'],
            ['550e8400-e29b-41d4-a716-446655440003', 'App\\Jobs\\ProcessPayment'],
        ] as [$uuid, $class]) {
            $record = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: Attempt::first(),
                jobClass: $class,
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            );
            $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));
            $repository->save($record);
        }

        $response = $this->getJson('/api/jobs-monitor/stats/overview?period=all');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['job_class', 'total', 'processed', 'failed', 'avg_duration_ms', 'max_duration_ms', 'retry_count'],
            ],
            'meta' => ['total'],
        ]);
        $response->assertJsonFragment(['job_class' => 'App\\Jobs\\SendInvoice', 'total' => 2]);
        $response->assertJsonFragment(['job_class' => 'App\\Jobs\\ProcessPayment', 'total' => 1]);
    }

    /**
     * @define-env enableApi
     */
    public function test_dlq_retry_endpoint_pushes_job_and_returns_new_uuid(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $record->setPayload(['email' => 'alice@test.com']);
        $repository->save($record);

        $queue = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('pushRaw')->once()->andReturnNull();
        $factory = \Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->with('redis')->once()->andReturn($queue);
        $this->app->instance(\Illuminate\Contracts\Queue\Factory::class, $factory);

        $response = $this->postJson("/api/jobs-monitor/dlq/{$uuid}/retry");

        $response->assertStatus(202);
        $response->assertJsonStructure(['data' => ['original_uuid', 'new_uuid', 'edited']]);
        $response->assertJsonPath('data.original_uuid', $uuid);
        $response->assertJsonPath('data.edited', false);
    }

    /**
     * @define-env enableApi
     */
    public function test_dlq_retry_endpoint_accepts_edited_payload(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $record->setPayload(['email' => 'old@test.com']);
        $repository->save($record);

        $queue = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('pushRaw')->once()->with(
            \Mockery::on(static fn (string $raw) => str_contains($raw, 'new@test.com')),
            'emails',
        );
        $factory = \Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->with('redis')->once()->andReturn($queue);
        $this->app->instance(\Illuminate\Contracts\Queue\Factory::class, $factory);

        $response = $this->postJson("/api/jobs-monitor/dlq/{$uuid}/retry", [
            'payload' => ['email' => 'new@test.com'],
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.edited', true);
    }

    /**
     * @define-env enableApi
     */
    public function test_dlq_retry_endpoint_returns_422_for_invalid_json_string(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);

        $response = $this->postJson('/api/jobs-monitor/dlq/550e8400-e29b-41d4-a716-446655440099/retry', [
            'payload' => '{not valid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Invalid JSON payload: Syntax error']);
    }

    /**
     * @define-env enableApi
     */
    public function test_dlq_delete_endpoint_removes_all_attempts(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440001';

        foreach ([1, 2] as $i) {
            $record = new JobRecord(
                id: new JobIdentifier($uuid),
                attempt: new Attempt($i),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: 'redis',
                queue: new QueueName('default'),
                startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            );
            $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
            $repository->save($record);
        }

        $response = $this->postJson("/api/jobs-monitor/dlq/{$uuid}/delete");

        $response->assertOk();
        $response->assertJsonPath('data.deleted', 2);
    }

    /**
     * @define-env enableApi
     */
    public function test_dlq_retry_endpoint_returns_403_when_gate_denies(): void
    {
        $this->app['config']->set('jobs-monitor.store_payload', true);
        $this->app['config']->set('jobs-monitor.dlq.authorization', 'manage-jobs-monitor');

        \Illuminate\Support\Facades\Gate::define('manage-jobs-monitor', static fn ($user, string $action) => false);

        $user = new class implements \Illuminate\Contracts\Auth\Authenticatable
        {
            public int $id = 1;

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return $this->id;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };

        $response = $this->actingAs($user)
            ->postJson('/api/jobs-monitor/dlq/550e8400-e29b-41d4-a716-446655440001/retry');

        $response->assertStatus(403);
    }

    /**
     * @define-env enableApi
     */
    public function test_time_series_returns_dense_buckets_with_data(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $now = new DateTimeImmutable;

        $processed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440010'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-10 minutes'),
        );
        $processed->markAsProcessed($now->modify('-10 minutes')->modify('+1 second'));

        $failed = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440011'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $now->modify('-5 minutes'),
        );
        $failed->markAsFailed($now->modify('-5 minutes')->modify('+1 second'), 'boom');

        $repository->save($processed);
        $repository->save($failed);

        $response = $this->getJson('/api/jobs-monitor/stats/time-series?period=1h');

        $response->assertOk();
        $response->assertJsonPath('data.period', '1h');
        $response->assertJsonPath('data.bucket_size', 'minute');
        $response->assertJsonStructure([
            'data' => [
                'period',
                'since',
                'until',
                'bucket_size',
                'buckets' => [
                    '*' => ['t', 'processed', 'failed'],
                ],
            ],
        ]);

        $json = $response->json();
        $buckets = $json['data']['buckets'];

        $totalProcessed = array_sum(array_column($buckets, 'processed'));
        $totalFailed = array_sum(array_column($buckets, 'failed'));
        self::assertSame(1, $totalProcessed);
        self::assertSame(1, $totalFailed);

        // Minute bucket over ~1h window should yield 60-62 contiguous points.
        self::assertGreaterThanOrEqual(60, count($buckets));
        self::assertLessThanOrEqual(62, count($buckets));
    }

    /**
     * @define-env enableApi
     */
    public function test_time_series_falls_back_to_default_period_on_invalid_input(): void
    {
        $response = $this->getJson('/api/jobs-monitor/stats/time-series?period=bogus');

        $response->assertOk();
        $response->assertJsonPath('data.period', '24h');
        $response->assertJsonPath('data.bucket_size', 'hour');
    }

    /**
     * @define-env enableApi
     */
    public function test_time_series_uses_day_bucket_for_long_periods(): void
    {
        $response = $this->getJson('/api/jobs-monitor/stats/time-series?period=30d');

        $response->assertOk();
        $response->assertJsonPath('data.period', '30d');
        $response->assertJsonPath('data.bucket_size', 'day');
    }

    /**
     * @param  Application  $app
     */
    protected function enableApi($app): void
    {
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    /**
     * @param  Application  $app
     */
    protected function useCustomApiPath($app): void
    {
        $app['config']->set('jobs-monitor.api.enabled', true);
        $app['config']->set('jobs-monitor.api.path', 'api/v2/monitor');
    }
}
