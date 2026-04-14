<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Persistence;

use Illuminate\Support\Facades\Schema;
use Yammi\JobsMonitor\Tests\TestCase;

final class FailureGroupMigrationTest extends TestCase
{
    public function test_failure_groups_table_exists(): void
    {
        self::assertTrue(Schema::hasTable('jobs_monitor_failure_groups'));
    }

    public function test_failure_groups_table_has_required_columns(): void
    {
        self::assertTrue(Schema::hasColumns('jobs_monitor_failure_groups', [
            'id',
            'fingerprint',
            'first_seen_at',
            'last_seen_at',
            'occurrences',
            'affected_job_classes',
            'last_job_uuid',
            'sample_exception_class',
            'sample_message',
            'sample_stack_trace',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_jobs_monitor_table_gets_failure_fingerprint_column(): void
    {
        self::assertTrue(Schema::hasColumn('jobs_monitor', 'failure_fingerprint'));
    }
}
