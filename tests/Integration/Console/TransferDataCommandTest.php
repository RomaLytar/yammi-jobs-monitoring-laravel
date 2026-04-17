<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Console;

use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Tests\TestCase;

final class TransferDataCommandTest extends TestCase
{
    private string $altDbPath;

    protected function setUp(): void
    {
        $this->altDbPath = sys_get_temp_dir() . '/jm_test_' . uniqid() . '.sqlite';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->altDbPath)) {
            unlink($this->altDbPath);
        }

        $lockPath = storage_path('app/.jobs-monitor-transfer.lock');
        if (file_exists($lockPath)) {
            unlink($lockPath);
        }
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.jm_alt', [
            'driver'   => 'sqlite',
            'database' => $this->altDbPath,
            'prefix'   => '',
        ]);
    }

    public function test_transfers_rows_to_destination_connection(): void
    {
        DB::table('jobs_monitor')->insert([
            'uuid'       => 'aaaaaaaa-0000-0000-0000-000000000001',
            'job_class'  => 'App\\Jobs\\TestJob',
            'connection' => 'sync',
            'queue'      => 'default',
            'status'     => 'completed',
            'attempt'    => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('jobs-monitor:transfer-data', ['--to' => 'jm_alt'])
            ->assertExitCode(0);

        self::assertSame(
            1,
            DB::connection('jm_alt')->table('jobs_monitor')->count(),
        );
    }

    public function test_all_package_tables_exist_on_destination_after_transfer(): void
    {
        $this->artisan('jobs-monitor:transfer-data', ['--to' => 'jm_alt'])
            ->assertExitCode(0);

        $schema = DB::connection('jm_alt')->getSchemaBuilder();

        foreach ($this->packageTables() as $table) {
            self::assertTrue($schema->hasTable($table), "Table {$table} missing on destination.");
        }
    }

    public function test_drops_source_tables_when_delete_source_flag_set(): void
    {
        $this->artisan('jobs-monitor:transfer-data', [
            '--to'            => 'jm_alt',
            '--delete-source' => true,
        ])->assertExitCode(0);

        self::assertFalse(
            DB::connection('testing')->getSchemaBuilder()->hasTable('jobs_monitor'),
        );
    }

    public function test_all_source_tables_dropped_in_reverse_order(): void
    {
        $this->artisan('jobs-monitor:transfer-data', [
            '--to'            => 'jm_alt',
            '--delete-source' => true,
        ])->assertExitCode(0);

        $schema = DB::connection('testing')->getSchemaBuilder();

        foreach ($this->packageTables() as $table) {
            self::assertFalse($schema->hasTable($table), "Table {$table} still exists on source.");
        }
    }

    public function test_transfer_is_idempotent_when_run_twice(): void
    {
        DB::table('jobs_monitor')->insert([
            'uuid'       => 'aaaaaaaa-0000-0000-0000-000000000002',
            'job_class'  => 'App\\Jobs\\TestJob',
            'connection' => 'sync',
            'queue'      => 'default',
            'status'     => 'completed',
            'attempt'    => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('jobs-monitor:transfer-data', ['--to' => 'jm_alt'])
            ->assertExitCode(0);

        $this->artisan('jobs-monitor:transfer-data', ['--from' => 'testing', '--to' => 'jm_alt'])
            ->assertExitCode(0);

        self::assertSame(
            1,
            DB::connection('jm_alt')->table('jobs_monitor')->count(),
        );
    }

    public function test_uses_config_connection_as_default_destination(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', 'jm_alt');

        $this->artisan('jobs-monitor:transfer-data')
            ->assertExitCode(0);
    }

    public function test_fails_when_no_destination_configured_and_no_to_option(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', null);

        $this->artisan('jobs-monitor:transfer-data')
            ->assertExitCode(1);
    }

    public function test_fails_when_source_and_destination_are_the_same(): void
    {
        $this->artisan('jobs-monitor:transfer-data', [
            '--from' => 'testing',
            '--to'   => 'testing',
        ])->assertExitCode(1);
    }

    public function test_fails_when_destination_connection_is_not_in_config(): void
    {
        $this->artisan('jobs-monitor:transfer-data', ['--to' => 'no_such_connection'])
            ->assertExitCode(1);
    }

    public function test_second_concurrent_invocation_is_rejected(): void
    {
        $lockPath = storage_path('app/.jobs-monitor-transfer.lock');
        $lock = fopen($lockPath, 'c');
        flock($lock, LOCK_EX | LOCK_NB);

        try {
            $this->artisan('jobs-monitor:transfer-data', ['--to' => 'jm_alt'])
                ->assertExitCode(1);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
        }
    }

    /**
     * @return list<string>
     */
    private function packageTables(): array
    {
        return [
            'jobs_monitor_settings',
            'jobs_monitor_alert_settings',
            'jobs_monitor_alert_mail_recipients',
            'jobs_monitor_built_in_rule_state',
            'jobs_monitor_alert_rules',
            'jobs_monitor_alert_rule_channels',
            'jobs_monitor_failure_groups',
            'jobs_monitor',
            'jobs_monitor_scheduled_runs',
            'jobs_monitor_duration_baselines',
            'jobs_monitor_duration_anomalies',
            'jobs_monitor_worker_heartbeats',
        ];
    }
}
