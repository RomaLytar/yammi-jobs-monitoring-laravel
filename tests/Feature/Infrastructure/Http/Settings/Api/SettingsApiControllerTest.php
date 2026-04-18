<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Tests\TestCase;

final class SettingsApiControllerTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    public function test_index_returns_features_list_with_alerts_disabled_by_default(): void
    {
        $response = $this->getJson('/api/jobs-monitor/settings');

        $response->assertOk();
<<<<<<< HEAD
        $response->assertJsonPath('data.features.0.key', 'general');
        $response->assertJsonPath('data.features.0.enabled', true);
        $response->assertJsonPath('data.features.1.key', 'alerts');
        $response->assertJsonPath('data.features.1.enabled', false);
=======
        $response->assertJsonPath('data.features.0.key', 'alerts');
        $response->assertJsonPath('data.features.0.enabled', false);
>>>>>>> origin/main
        $response->assertJsonStructure([
            'data' => [
                'features' => [
                    ['key', 'name', 'description', 'enabled'],
                ],
            ],
        ]);
    }

    public function test_index_reflects_db_enabled_alerts(): void
    {
        $repo = $this->app->make(AlertSettingsRepository::class);
        $repo->save(new AlertSettings(true, null, null, new EmailRecipientList([])));

        $response = $this->getJson('/api/jobs-monitor/settings');

        $response->assertOk();
<<<<<<< HEAD
        $response->assertJsonPath('data.features.1.enabled', true);
=======
        $response->assertJsonPath('data.features.0.enabled', true);
>>>>>>> origin/main
    }

    public function test_index_reflects_config_enabled_alerts_when_db_unset(): void
    {
        $this->app['config']->set('jobs-monitor.alerts.enabled', true);
        // Re-bind resolver to pick up the new config snapshot
        $this->app->forgetInstance(\Yammi\JobsMonitor\Application\Service\AlertConfigResolver::class);

        $response = $this->getJson('/api/jobs-monitor/settings');

        $response->assertOk();
<<<<<<< HEAD
        $response->assertJsonPath('data.features.1.enabled', true);
=======
        $response->assertJsonPath('data.features.0.enabled', true);
>>>>>>> origin/main
    }

    public function test_index_returns_403_when_gate_denies(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jobs-monitor.settings');
        Gate::define('jobs-monitor.settings', static fn () => false);

        $response = $this->getJson('/api/jobs-monitor/settings');

        $response->assertForbidden();
    }

    public function test_index_returns_200_when_gate_allows(): void
    {
        $this->app['config']->set('jobs-monitor.settings.authorization', 'jobs-monitor.settings');
        Gate::define('jobs-monitor.settings', static fn ($user) => true);

        $response = $this->actingAs($this->fakeUser())->getJson('/api/jobs-monitor/settings');

        $response->assertOk();
    }

    private function fakeUser(): Authenticatable
    {
        return new class implements Authenticatable
        {
            public int $id = 1;

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };
    }
}
