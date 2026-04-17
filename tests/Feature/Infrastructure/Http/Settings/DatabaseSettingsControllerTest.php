<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings;

use Illuminate\Support\Facades\Gate;
use Yammi\JobsMonitor\Tests\TestCase;

final class DatabaseSettingsControllerTest extends TestCase
{
    private string $altDbPath;

    protected function setUp(): void
    {
        $this->altDbPath = sys_get_temp_dir().'/jm_ctrl_test_'.uniqid().'.sqlite';
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
            'driver' => 'sqlite',
            'database' => $this->altDbPath,
            'prefix' => '',
        ]);
    }

    // --- index ---

    public function test_index_is_accessible(): void
    {
        $this->get('/jobs-monitor/settings/database')->assertOk();
    }

    public function test_index_shows_page_heading(): void
    {
        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('Database Connection');
    }

    public function test_index_shows_default_connection_name(): void
    {
        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('testing');
    }

    public function test_index_shows_back_link_to_settings(): void
    {
        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('Back to settings');
    }

    public function test_index_shows_not_configured_when_no_monitor_connection(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', null);

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('Not configured');
    }

    public function test_index_shows_monitor_connection_name_when_configured(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', 'jm_alt');

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('jm_alt');
    }

    public function test_index_shows_transfer_block_when_monitor_connection_configured(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', 'jm_alt');
        touch($this->altDbPath);

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('Transfer Data');
    }

    public function test_index_hides_transfer_block_when_no_monitor_connection(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', null);

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertDontSee('Transfer Data');
    }

    public function test_index_returns_403_when_gate_denies(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jm-settings');
        Gate::define('jm-settings', static fn () => false);

        $this->get('/jobs-monitor/settings/database')->assertForbidden();
    }

    public function test_index_shows_reachable_status_for_default_connection(): void
    {
        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('Reachable');
    }

    public function test_index_shows_migrate_hint_when_monitor_tables_not_created(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', 'jm_alt');

        // Alt DB file exists but has no tables yet
        touch($this->altDbPath);

        $this->get('/jobs-monitor/settings/database')
            ->assertOk()
            ->assertSee('Not migrated');
    }

    // --- settings index card ---

    public function test_settings_index_shows_database_feature_card(): void
    {
        $this->get('/jobs-monitor/settings')
            ->assertOk()
            ->assertSee('Database Connection');
    }

    // --- transfer ---

    public function test_transfer_requires_to_field(): void
    {
        $this->post('/jobs-monitor/settings/database/transfer', ['from' => 'testing'])
            ->assertSessionHasErrors('to');
    }

    public function test_transfer_requires_from_field(): void
    {
        $this->post('/jobs-monitor/settings/database/transfer', ['to' => 'jm_alt'])
            ->assertSessionHasErrors('from');
    }

    public function test_transfer_fails_when_from_equals_to(): void
    {
        $this->post('/jobs-monitor/settings/database/transfer', [
            'from' => 'testing',
            'to' => 'testing',
        ])->assertSessionHasErrors('to');
    }

    public function test_transfer_redirects_with_success_on_valid_connections(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', 'jm_alt');

        $this->post('/jobs-monitor/settings/database/transfer', [
            'from' => 'testing',
            'to' => 'jm_alt',
        ])
            ->assertRedirect('/jobs-monitor/settings/database')
            ->assertSessionHas('jobs_monitor_status');
    }

    public function test_transfer_shows_error_when_command_fails(): void
    {
        $this->post('/jobs-monitor/settings/database/transfer', [
            'from' => 'testing',
            'to' => 'nonexistent_connection',
        ])
            ->assertRedirect()
            ->assertSessionHas('jobs_monitor_error');
    }

    public function test_transfer_returns_403_when_gate_denies(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jm-settings');
        Gate::define('jm-settings', static fn () => false);

        $this->post('/jobs-monitor/settings/database/transfer', [
            'from' => 'testing',
            'to' => 'jm_alt',
        ])->assertForbidden();
    }
}
