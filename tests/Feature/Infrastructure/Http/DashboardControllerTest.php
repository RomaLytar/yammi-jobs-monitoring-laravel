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

final class DashboardControllerTest extends TestCase
{
    public function test_dashboard_returns_ok(): void
    {
        $response = $this->get('/jobs-monitor');

        $response->assertOk();
    }

    public function test_dashboard_renders_filter_dropdowns(): void
    {
        $response = $this->get('/jobs-monitor');

        $response->assertOk();
        $response->assertSee('name="status"', false);
        $response->assertSee('name="queue"', false);
        $response->assertSee('name="connection"', false);
        $response->assertSee('name="failure_category"', false);
        $response->assertSee('All queues');
        $response->assertSee('All connections');
    }

    public function test_dashboard_filters_by_queue(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $default = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $emails = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendEmail',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $repository->save($default);
        $repository->save($emails);

        $response = $this->get('/jobs-monitor?period=all&queue=emails');

        $response->assertOk();
        $response->assertSee('SendEmail');
        $response->assertDontSee('SendInvoice');
    }

    public function test_dashboard_populates_queue_dropdown_from_distinct_queues(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        foreach (['alpha', 'beta'] as $i => $queue) {
            $record = new JobRecord(
                id: new JobIdentifier(sprintf('550e8400-e29b-41d4-a716-44665544000%d', $i + 1)),
                attempt: Attempt::first(),
                jobClass: 'App\\Jobs\\SendInvoice',
                connection: 'redis',
                queue: new QueueName($queue),
                startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            );
            $repository->save($record);
        }

        $response = $this->get('/jobs-monitor?period=all');

        $response->assertOk();
        $response->assertSee('value="alpha"', false);
        $response->assertSee('value="beta"', false);
    }

    public function test_dashboard_renders_view(): void
    {
        $response = $this->get('/jobs-monitor');

        $response->assertViewIs('jobs-monitor::dashboard');
    }

    public function test_dashboard_displays_recent_jobs(): void
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

        $response = $this->get('/jobs-monitor?period=all');

        $response->assertOk();
        $response->assertSee('SendInvoice');
        $response->assertSee('processed');
    }

    public function test_dashboard_displays_failed_job(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\ProcessPayment',
            connection: 'redis',
            queue: new QueueName('payments'),
            startedAt: new DateTimeImmutable,
        );
        $record->markAsFailed(new DateTimeImmutable, 'RuntimeException: Payment gateway timeout');
        $repository->save($record);

        $response = $this->get('/jobs-monitor');

        $response->assertOk();
        $response->assertSee('ProcessPayment');
        $response->assertSee('failed');
    }

    public function test_dashboard_displays_processing_job(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440002'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SyncData',
            connection: 'database',
            queue: new QueueName('sync'),
            startedAt: new DateTimeImmutable,
        );
        $repository->save($record);

        $response = $this->get('/jobs-monitor');

        $response->assertOk();
        $response->assertSee('SyncData');
        $response->assertSee('processing');
    }

    /**
     * @define-env useCustomUiPath
     */
    public function test_dashboard_respects_custom_path(): void
    {
        $response = $this->get('/admin/monitor');

        $response->assertOk();
    }

    /**
     * @define-env disableUi
     */
    public function test_dashboard_returns_404_when_ui_disabled(): void
    {
        $response = $this->get('/jobs-monitor');

        $response->assertNotFound();
    }

    public function test_dashboard_shows_queue_name(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440003'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendEmail',
            connection: 'redis',
            queue: new QueueName('emails'),
            startedAt: new DateTimeImmutable,
        );
        $repository->save($record);

        $response = $this->get('/jobs-monitor');

        $response->assertOk();
        $response->assertSee('emails');
    }

    public function test_dashboard_shows_duration_for_completed_jobs(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = new JobRecord(
            id: new JobIdentifier('550e8400-e29b-41d4-a716-446655440004'),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\GenerateReport',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:05Z'));
        $repository->save($record);

        $response = $this->get('/jobs-monitor?period=all');

        $response->assertOk();
        $response->assertSee('5.00s');
    }

    /**
     * @param  Application  $app
     */
    protected function useCustomUiPath($app): void
    {
        $app['config']->set('jobs-monitor.ui.path', 'admin/monitor');
    }

    /**
     * @param  Application  $app
     */
    protected function disableUi($app): void
    {
        $app['config']->set('jobs-monitor.ui.enabled', false);
    }
}
