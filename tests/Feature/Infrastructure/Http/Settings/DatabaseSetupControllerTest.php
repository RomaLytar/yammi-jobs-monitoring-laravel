<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Yammi\JobsMonitor\Infrastructure\Job\TransferMonitorDataJob;
use Yammi\JobsMonitor\Tests\TestCase;

/**
 * Covers the POST /settings/database/setup endpoint that creates the
 * monitor database and transfers data from the default connection.
 */
final class DatabaseSetupControllerTest extends TestCase
{
    private string $altDbPath;

    protected function setUp(): void
    {
        $this->altDbPath = sys_get_temp_dir().'/jm_setup_'.uniqid().'.sqlite';
        parent::setUp();
        $this->cleanTransferState();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->altDbPath)) {
            unlink($this->altDbPath);
        }

        $this->cleanTransferState();
    }

    private function cleanTransferState(): void
    {
        $lockPath = storage_path('app/.jobs-monitor-transfer.lock');
        if (file_exists($lockPath)) {
            unlink($lockPath);
        }

        TransferMonitorDataJob::clearStatus();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Touch the file so the connection is reachable during app boot.
        // Individual tests that want "unreachable" behaviour override the connection.
        touch($this->altDbPath);

        $app['config']->set('database.connections.jm_setup', [
            'driver' => 'sqlite',
            'database' => $this->altDbPath,
            'prefix' => '',
        ]);
        $app['config']->set('jobs-monitor.database.connection', 'jm_setup');
    }

    public function test_setup_redirects_to_dashboard_on_success(): void
    {
        $this->post('/jobs-monitor/settings/database/setup')
            ->assertRedirect(route('jobs-monitor.dashboard'));
    }

    public function test_setup_flashes_success_message(): void
    {
        $this->post('/jobs-monitor/settings/database/setup')
            ->assertSessionHas('jobs_monitor_status');
    }

    public function test_setup_creates_tables_on_monitor_connection(): void
    {
        $this->post('/jobs-monitor/settings/database/setup');

        $this->assertTrue(
            DB::connection('jm_setup')
                ->getSchemaBuilder()
                ->hasTable('jobs_monitor'),
        );
    }

    public function test_setup_flashes_error_when_connection_does_not_exist(): void
    {
        // Override to a connection name that has no entry in database.connections.
        $this->app['config']->set('jobs-monitor.database.connection', 'totally_nonexistent_conn');

        $this->post('/jobs-monitor/settings/database/setup')
            ->assertSessionHas('jobs_monitor_error');
    }

    public function test_setup_redirects_back_on_failure(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', 'totally_nonexistent_conn');

        $this->post('/jobs-monitor/settings/database/setup')
            ->assertRedirect();
    }

    public function test_setup_requires_authorization(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jm-settings');
        Gate::define('jm-settings', static fn () => false);

        $this->post('/jobs-monitor/settings/database/setup')
            ->assertForbidden();
    }

    // --- run-migrations ---

    public function test_run_migrations_creates_tables_on_active_connection(): void
    {
        // jm_setup is the active connection (file-based SQLite, starts with no tables)
        $this->post('/jobs-monitor/settings/database/run-migrations');

        $this->assertTrue(
            $this->app['db']->connection('jm_setup')->getSchemaBuilder()->hasTable('jobs_monitor'),
        );
    }

    public function test_run_migrations_transfers_existing_rows_to_monitor(): void
    {
        DB::table('jobs_monitor')->insert([
            'uuid' => 'dddddddd-0000-0000-0000-000000000001',
            'job_class' => 'App\\Jobs\\MigrateTestJob',
            'connection' => 'sync',
            'queue' => 'default',
            'status' => 'completed',
            'attempt' => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/jobs-monitor/settings/database/run-migrations');

        $this->assertSame(1, DB::connection('jm_setup')->table('jobs_monitor')->count());
    }

    public function test_run_migrations_drops_source_tables_after_transfer(): void
    {
        $this->post('/jobs-monitor/settings/database/run-migrations');

        $this->assertFalse(
            DB::connection('testing')->getSchemaBuilder()->hasTable('jobs_monitor'),
        );
    }

    public function test_run_migrations_flashes_success(): void
    {
        $this->post('/jobs-monitor/settings/database/run-migrations')
            ->assertSessionHas('jobs_monitor_status');
    }

    public function test_run_migrations_requires_authorization(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jm-settings');
        Gate::define('jm-settings', static fn () => false);

        $this->post('/jobs-monitor/settings/database/run-migrations')
            ->assertForbidden();
    }

    public function test_setup_copies_existing_rows_from_default_connection(): void
    {
        DB::table('jobs_monitor')->insert([
            'uuid' => 'bbbbbbbb-0000-0000-0000-000000000001',
            'job_class' => 'App\\Jobs\\SetupTestJob',
            'connection' => 'sync',
            'queue' => 'default',
            'status' => 'completed',
            'attempt' => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/jobs-monitor/settings/database/setup');

        $count = DB::connection('jm_setup')
            ->table('jobs_monitor')
            ->count();

        $this->assertSame(1, $count);
    }

    // --- transfer-status ---

    public function test_transfer_status_returns_idle_when_no_transfer_in_progress(): void
    {
        $this->getJson('/jobs-monitor/settings/database/transfer-status')
            ->assertOk()
            ->assertJson(['status' => 'idle']);
    }

    public function test_transfer_status_requires_authorization(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jm-settings');
        Gate::define('jm-settings', static fn () => false);

        $this->getJson('/jobs-monitor/settings/database/transfer-status')
            ->assertForbidden();
    }

    public function test_transfer_status_returns_pending_after_dispatch_before_completion(): void
    {
        TransferMonitorDataJob::writeStatus(['status' => 'pending', 'from' => 'testing', 'to' => 'jm_setup']);

        $this->getJson('/jobs-monitor/settings/database/transfer-status')
            ->assertOk()
            ->assertJson(['status' => 'pending']);
    }

    public function test_index_clears_done_status_and_exposes_it_as_view_variable(): void
    {
        TransferMonitorDataJob::writeStatus(['status' => 'done', 'from' => 'testing', 'to' => 'jm_setup']);

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertViewHas('transferDone');

        $this->assertSame(['status' => 'idle'], TransferMonitorDataJob::readStatus());
    }

    public function test_index_clears_failed_status_and_exposes_it_as_view_variable(): void
    {
        TransferMonitorDataJob::writeStatus(['status' => 'failed', 'error' => 'Something broke.']);

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertViewHas('transferFailed', 'Something broke.');

        $this->assertSame(['status' => 'idle'], TransferMonitorDataJob::readStatus());
    }

    public function test_index_exposes_pending_status_for_polling_when_transfer_running(): void
    {
        TransferMonitorDataJob::writeStatus(['status' => 'running', 'from' => 'testing', 'to' => 'jm_setup']);

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertViewHas('transferPending');

        // Status file must NOT be cleared while still running.
        $this->assertNotSame('idle', TransferMonitorDataJob::readStatus()['status']);

        TransferMonitorDataJob::clearStatus();
    }
}
