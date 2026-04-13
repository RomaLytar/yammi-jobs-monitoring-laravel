<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Settings;

use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;
use Yammi\JobsMonitor\Tests\TestCase;

/**
 * End-to-end check: a rule created via the UI/API endpoint is picked up
 * by AlertConfigResolver on the very next evaluation tick, without a
 * service-provider rebuild or app restart.
 */
final class AlertRulesIntegrationTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('jobs-monitor.api.enabled', true);
        $app['config']->set('jobs-monitor.alerts.enabled', true);
    }

    public function test_rule_created_via_api_appears_in_next_resolver_tick(): void
    {

        $response = $this->postJson('/api/jobs-monitor/settings/alerts/rules', [
            'key' => 'my_payment_alert',
            'trigger' => 'failure_rate',
            'window' => '5m',
            'threshold' => 99,
            'cooldown_minutes' => 30,
            'channels' => ['slack'],
            'enabled' => true,
            'position' => 0,
        ]);

        $response->assertOk();

        $resolver = $this->app->make(AlertConfigResolver::class);
        $config = $resolver->resolve();

        self::assertTrue($config->enabled);

        $thresholds = array_map(static fn ($r) => $r->threshold, $config->rules);
        self::assertContains(99, $thresholds);
    }

    public function test_built_in_disabled_via_api_disappears_from_resolver(): void
    {
        $beforeKeys = $this->resolvedTriggerValues();
        self::assertContains('critical', $beforeKeys);

        $response = $this->postJson('/api/jobs-monitor/settings/alerts/rules/built-in/critical_failure/toggle', [
            'enabled' => false,
        ]);
        $response->assertOk();

        $afterKeys = $this->resolvedTriggerValues();
        self::assertNotContains('critical', $afterKeys);
    }

    /**
     * @return list<?string>
     */
    private function resolvedTriggerValues(): array
    {
        $resolver = $this->app->make(AlertConfigResolver::class);

        return array_map(
            static fn ($r) => $r->triggerValue,
            $resolver->resolve()->rules,
        );
    }
}
