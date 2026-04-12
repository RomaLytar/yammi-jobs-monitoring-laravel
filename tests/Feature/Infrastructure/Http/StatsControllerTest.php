<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\TestCase;

final class StatsControllerTest extends TestCase
{
    public function test_stats_page_returns_ok(): void
    {
        $response = $this->get('/jobs-monitor/stats');

        $response->assertOk();
        $response->assertViewIs('jobs-monitor::stats');
    }

    public function test_stats_page_shows_empty_state_without_records(): void
    {
        $response = $this->get('/jobs-monitor/stats?period=all');

        $response->assertOk();
        $response->assertSee('No jobs recorded');
    }

    public function test_stats_page_shows_aggregated_counts(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $a = $this->makeRecord('550e8400-e29b-41d4-a716-446655440001', 'App\\Jobs\\SendInvoice');
        $a->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));
        $b = $this->makeRecord('550e8400-e29b-41d4-a716-446655440002', 'App\\Jobs\\SendInvoice');
        $b->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'timeout');

        $repository->save($a);
        $repository->save($b);

        $response = $this->get('/jobs-monitor/stats?period=all');

        $response->assertOk();
        $response->assertSee('SendInvoice');
        $response->assertSee('Most failing jobs');
        $response->assertSee('Slowest jobs');
        $response->assertSee('All job classes');
    }

    public function test_stats_page_respects_period_filter(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $old = $this->makeRecord('550e8400-e29b-41d4-a716-446655440001', 'App\\Jobs\\OldJob', new DateTimeImmutable('-2 hours'));
        $old->markAsProcessed(new DateTimeImmutable('-119 minutes'));
        $repository->save($old);

        $response = $this->get('/jobs-monitor/stats?period=1h');

        $response->assertOk();
        $response->assertDontSee('OldJob');
    }

    private function makeRecord(string $uuid, string $jobClass, ?DateTimeImmutable $startedAt = null): JobRecord
    {
        return new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: $jobClass,
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $startedAt ?? new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }
}
