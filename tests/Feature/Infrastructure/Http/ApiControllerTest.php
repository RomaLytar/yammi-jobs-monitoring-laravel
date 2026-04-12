<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
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

        $response = $this->getJson('/api/jobs-monitor/jobs');

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
