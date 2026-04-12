<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Console;

use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;
use Yammi\JobsMonitor\Tests\TestCase;

final class PruneJobRecordsCommandTest extends TestCase
{
    public function test_prune_deletes_records_older_than_given_days(): void
    {
        $this->createRecord('550e8400-e29b-41d4-a716-446655440001', now()->subDays(60));
        $this->createRecord('550e8400-e29b-41d4-a716-446655440002', now()->subDays(40));
        $this->createRecord('550e8400-e29b-41d4-a716-446655440003', now()->subDays(10));

        $this->artisan('jobs-monitor:prune', ['--days' => 30])
            ->expectsOutputToContain('Pruned 2 records')
            ->assertSuccessful();

        self::assertSame(1, JobRecordModel::query()->count());
    }

    public function test_prune_uses_config_default_when_no_option_given(): void
    {
        config()->set('jobs-monitor.retention_days', 15);

        $this->createRecord('550e8400-e29b-41d4-a716-446655440001', now()->subDays(20));
        $this->createRecord('550e8400-e29b-41d4-a716-446655440002', now()->subDays(5));

        $this->artisan('jobs-monitor:prune')
            ->expectsOutputToContain('Pruned 1 record')
            ->assertSuccessful();

        self::assertSame(1, JobRecordModel::query()->count());
    }

    public function test_prune_outputs_zero_when_nothing_to_delete(): void
    {
        $this->createRecord('550e8400-e29b-41d4-a716-446655440001', now()->subDays(5));

        $this->artisan('jobs-monitor:prune', ['--days' => 30])
            ->expectsOutputToContain('Pruned 0 records')
            ->assertSuccessful();

        self::assertSame(1, JobRecordModel::query()->count());
    }

    private function createRecord(string $uuid, \DateTimeInterface $createdAt): void
    {
        JobRecordModel::query()->create([
            'uuid' => $uuid,
            'attempt' => 1,
            'job_class' => 'App\\Jobs\\TestJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'started_at' => $createdAt,
            'finished_at' => $createdAt,
            'duration_ms' => 100,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
