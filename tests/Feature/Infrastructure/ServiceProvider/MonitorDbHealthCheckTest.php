<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\ServiceProvider;

use Yammi\JobsMonitor\Tests\TestCase;

/**
 * Covers the boot-time health check that fires when
 * `jobs-monitor.database.connection` is configured but the database
 * is unreachable (missing file, wrong host, etc.).
 */
final class MonitorDbHealthCheckTest extends TestCase
{
    private string $badDbPath;

    protected function setUp(): void
    {
        // Path must be set BEFORE parent::setUp() so defineEnvironment can reference it.
        // We intentionally do NOT touch/create the file — this makes the connection fail.
        $this->badDbPath = sys_get_temp_dir().'/jm_health_bad_'.uniqid().'.sqlite';

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

        $app['config']->set('database.connections.jm_health_bad', [
            'driver' => 'sqlite',
            'database' => $this->badDbPath,
            'prefix' => '',
        ]);
        $app['config']->set('jobs-monitor.database.connection', 'jm_health_bad');
    }

    public function test_sets_unreachable_flag_when_monitor_db_missing(): void
    {
        $this->assertTrue($this->app->bound('jobs-monitor.db_unreachable'));
    }

    public function test_preserves_master_switch_when_monitor_db_unreachable(): void
    {
        // The db_unreachable flag now degrades listeners/schedules without
        // flipping the host-owned master switch. See JobsMonitorServiceProvider.
        $this->assertTrue((bool) config('jobs-monitor.enabled'));
    }
}
