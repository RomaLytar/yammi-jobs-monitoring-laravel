<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use Yammi\JobsMonitor\Tests\TestCase;

/**
 * Covers RequireMonitorAuth — the guard-agnostic middleware that is
 * unconditionally appended to the dashboard route group. Config should
 * ship with `allow_unauthenticated=false` so hosts stay fail-closed by
 * default, and a check on any configured guard must let an
 * authenticated visitor through.
 */
final class RequireMonitorAuthTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Restore production fail-closed default for this test class.
        $app['config']->set('jobs-monitor.ui.allow_unauthenticated', false);
    }

    public function test_published_config_is_fail_closed_by_default(): void
    {
        $config = require __DIR__.'/../../../../config/jobs-monitor.php';

        self::assertSame(['web'], $config['ui']['middleware']);
        self::assertFalse($config['ui']['allow_unauthenticated']);
        self::assertNull($config['ui']['guards']);
    }

    public function test_anonymous_dashboard_request_is_rejected_with_403(): void
    {
        $this->get('/jobs-monitor')->assertForbidden();
    }

    public function test_authenticated_visitor_reaches_dashboard(): void
    {
        $this->authenticateUser();

        $this->get('/jobs-monitor')->assertOk();
    }

    public function test_allow_unauthenticated_opt_out_lets_anonymous_visit(): void
    {
        $this->app['config']->set('jobs-monitor.ui.allow_unauthenticated', true);

        $this->get('/jobs-monitor')->assertOk();
    }

    public function test_configured_guard_list_is_honoured(): void
    {
        // Only accept sessions authenticated through a guard that is
        // guaranteed to fail — the web guard is still the only one with
        // an authenticated user, so access must be denied.
        $this->app['config']->set('jobs-monitor.ui.guards', ['api']);
        $this->app['config']->set('auth.guards.api', ['driver' => 'token', 'provider' => 'users']);
        $this->authenticateUser(); // authenticates via the default ('web') guard

        $this->get('/jobs-monitor')->assertForbidden();
    }
}
