<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Authorization;

use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Tests\TestCase;

final class SettingsGateTest extends TestCase
{
    public function test_denies_unauthenticated_request_by_default(): void
    {
        $this->app['config']->set('jobs-monitor.settings.allow_unauthenticated', false);
        $this->app['config']->set('jobs-monitor.settings.authorization', null);

        $this->assertAborts(fn () => $this->app->make(SettingsGate::class)->authorize());
    }

    public function test_allows_authenticated_user_when_no_ability_set(): void
    {
        $this->app['config']->set('jobs-monitor.settings.allow_unauthenticated', false);
        $this->app['config']->set('jobs-monitor.settings.authorization', null);

        $this->authenticateUser();

        $this->app->make(SettingsGate::class)->authorize();

        $this->addToAssertionCount(1);
    }

    public function test_delegates_to_gate_when_ability_configured(): void
    {
        $this->app['config']->set('jobs-monitor.settings.allow_unauthenticated', false);
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jm-settings');
        Gate::define('jm-settings', static fn ($user = null): bool => true);

        $this->app->make(SettingsGate::class)->authorize();

        $this->addToAssertionCount(1);
    }

    public function test_aborts_when_ability_denies_even_for_authenticated_user(): void
    {
        $this->app['config']->set('jobs-monitor.settings.allow_unauthenticated', false);
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jm-settings');
        Gate::define('jm-settings', static fn ($user = null): bool => false);
        $this->authenticateUser();

        $this->assertAborts(fn () => $this->app->make(SettingsGate::class)->authorize());
    }

    public function test_allow_unauthenticated_opt_out_bypasses_auth(): void
    {
        $this->app['config']->set('jobs-monitor.settings.allow_unauthenticated', true);
        $this->app['config']->set('jobs-monitor.settings.authorization', null);

        $this->app->make(SettingsGate::class)->authorize();

        $this->addToAssertionCount(1);
    }

    private function assertAborts(callable $callable): void
    {
        try {
            $callable();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            return;
        }

        $this->fail('Expected SettingsGate to abort(403).');
    }
}
