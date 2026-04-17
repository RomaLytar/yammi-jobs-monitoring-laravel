<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings;

use Yammi\JobsMonitor\Tests\TestCase;

/**
 * Verifies that MonitorDbHealthMiddleware shows the "unreachable" page
 * when the monitor connection fails, and lets through requests to the
 * Database Settings routes so the operator can reach the fix UI.
 */
final class MonitorDbBlockerTest extends TestCase
{
    private string $badDbPath;

    protected function setUp(): void
    {
        // Non-existent file — middleware ping will fail → blocker shown.
        // Set BEFORE parent::setUp() so defineEnvironment can reference it.
        $this->badDbPath = sys_get_temp_dir() . '/jm_blocker_bad_' . uniqid() . '.sqlite';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->badDbPath)) {
            unlink($this->badDbPath);
        }
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.jm_blocker', [
            'driver'   => 'sqlite',
            'database' => $this->badDbPath, // file does not exist → unreachable
            'prefix'   => '',
        ]);
        $app['config']->set('jobs-monitor.database.connection', 'jm_blocker');
    }

    // --- blocker shown when DB unreachable ---

    public function test_blocker_shown_on_dashboard_when_db_unreachable(): void
    {
        $this->get('/jobs-monitor/')
            ->assertOk()
            ->assertSee('Monitor database unreachable');
    }

    public function test_blocker_shown_on_failures_page_when_db_unreachable(): void
    {
        $this->get('/jobs-monitor/failures')
            ->assertOk()
            ->assertSee('Monitor database unreachable');
    }

    public function test_blocker_contains_artisan_command(): void
    {
        $this->get('/jobs-monitor/')
            ->assertOk()
            ->assertSee('jobs-monitor:transfer-data');
    }

    public function test_blocker_shows_setup_button_pointing_to_setup_route(): void
    {
        $this->get('/jobs-monitor/')
            ->assertOk()
            ->assertSee(route('jobs-monitor.settings.database.setup'));
    }

    public function test_blocker_shows_configured_connection_name(): void
    {
        $this->get('/jobs-monitor/')
            ->assertOk()
            ->assertSee('jm_blocker');
    }

    // --- DB settings routes always pass through ---

    public function test_database_settings_page_accessible_when_db_unreachable(): void
    {
        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertDontSee('Monitor database unreachable');
    }

    // --- no blocker when monitor connection not configured ---

    public function test_no_blocker_when_monitor_connection_not_configured(): void
    {
        // Override: remove monitor connection so middleware skips.
        $this->app['config']->set('jobs-monitor.database.connection', null);

        $this->get('/jobs-monitor/')
            ->assertOk()
            ->assertDontSee('Monitor database unreachable');
    }

    // --- not-migrated blocker ---

    public function test_not_migrated_blocker_shown_when_active_connection_has_no_tables(): void
    {
        // Remove monitor connection so the default (testing) is active,
        // then wipe jobs_monitor table to simulate un-migrated state.
        $this->app['config']->set('jobs-monitor.database.connection', null);
        $this->app['db']->connection('testing')->getSchemaBuilder()->dropIfExists('jobs_monitor');

        $this->get('/jobs-monitor/')
            ->assertOk()
            ->assertSee('Migrations not applied');
    }

    public function test_not_migrated_blocker_contains_migrate_button(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', null);
        $this->app['db']->connection('testing')->getSchemaBuilder()->dropIfExists('jobs_monitor');

        $this->get('/jobs-monitor/')
            ->assertOk()
            ->assertSee(route('jobs-monitor.settings.database.run-migrations'));
    }

    public function test_database_settings_page_accessible_when_not_migrated(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', null);
        $this->app['db']->connection('testing')->getSchemaBuilder()->dropIfExists('jobs_monitor');

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertDontSee('Migrations not applied');
    }
}
