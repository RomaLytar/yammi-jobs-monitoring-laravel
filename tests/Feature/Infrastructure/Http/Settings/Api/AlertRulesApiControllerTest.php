<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings\Api;

use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class AlertRulesApiControllerTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    public function test_index_returns_built_ins_and_user_rules(): void
    {
        $this->rulesRepo()->save($this->sampleUserRule('my_custom'));

        $response = $this->withoutExceptionHandling()->getJson('/api/jobs-monitor/settings/alerts/rules');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'built_in_rules' => [['key', 'trigger', 'threshold', 'channels', 'effectively_enabled', 'has_override']],
                'user_rules' => [['id', 'key', 'trigger', 'threshold', 'channels', 'enabled']],
            ],
        ]);
        $response->assertJsonPath('data.user_rules.0.key', 'my_custom');
    }

    public function test_store_creates_rule_and_returns_resource(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/rules', $this->validPayload());

        $response->assertOk();
        $response->assertJsonPath('data.key', 'my_rule');
        $response->assertJsonPath('data.threshold', 10);
        self::assertCount(1, $this->rulesRepo()->all());
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/rules', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['key', 'trigger', 'threshold', 'cooldown_minutes', 'channels']);
    }

    public function test_show_returns_rule(): void
    {
        $persisted = $this->rulesRepo()->save($this->sampleUserRule('to_show'));

        $response = $this->getJson('/api/jobs-monitor/settings/alerts/rules/'.$persisted->id());

        $response->assertOk();
        $response->assertJsonPath('data.key', 'to_show');
    }

    public function test_show_returns_404_for_missing_rule(): void
    {
        $response = $this->getJson('/api/jobs-monitor/settings/alerts/rules/999');

        $response->assertNotFound();
    }

    public function test_update_persists_changes(): void
    {
        $persisted = $this->rulesRepo()->save($this->sampleUserRule('to_update'));

        $response = $this->putJson(
            '/api/jobs-monitor/settings/alerts/rules/'.$persisted->id(),
            $this->validPayload(['key' => 'to_update', 'threshold' => 77]),
        );

        $response->assertOk();
        $response->assertJsonPath('data.threshold', 77);
    }

    public function test_destroy_returns_204(): void
    {
        $persisted = $this->rulesRepo()->save($this->sampleUserRule('to_delete'));

        $response = $this->deleteJson('/api/jobs-monitor/settings/alerts/rules/'.$persisted->id());

        $response->assertNoContent();
        self::assertNull($this->rulesRepo()->findById($persisted->id()));
    }

    public function test_destroy_returns_404_when_missing(): void
    {
        $response = $this->deleteJson('/api/jobs-monitor/settings/alerts/rules/999');

        $response->assertNotFound();
    }

    public function test_toggle_built_in_disables_and_returns_overview(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/rules/built-in/critical_failure/toggle', [
            'enabled' => false,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['built_in_rules', 'user_rules']]);
        self::assertFalse($this->stateRepo()->findEnabled('critical_failure'));
    }

    public function test_toggle_built_in_clears_override_when_null(): void
    {
        $this->stateRepo()->setEnabled('critical_failure', false);

        $response = $this->postJson('/api/jobs-monitor/settings/alerts/rules/built-in/critical_failure/toggle', [
            'enabled' => null,
        ]);

        $response->assertOk();
        self::assertNull($this->stateRepo()->findEnabled('critical_failure'));
    }

    public function test_toggle_built_in_404_on_unknown_key(): void
    {
        $response = $this->postJson('/api/jobs-monitor/settings/alerts/rules/built-in/nonexistent/toggle', [
            'enabled' => false,
        ]);

        $response->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'my_rule',
            'trigger' => 'failure_rate',
            'window' => '5m',
            'threshold' => 10,
            'cooldown_minutes' => 15,
            'channels' => ['slack'],
            'enabled' => true,
            'position' => 0,
        ], $overrides);
    }

    private function sampleUserRule(string $key): ManagedAlertRule
    {
        return new ManagedAlertRule(
            id: null,
            key: $key,
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '5m',
                threshold: 5,
                channels: ['slack'],
                cooldownMinutes: 10,
            ),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        );
    }

    private function rulesRepo(): ManagedAlertRuleRepository
    {
        return $this->app->make(ManagedAlertRuleRepository::class);
    }

    private function stateRepo(): BuiltInRuleStateRepository
    {
        return $this->app->make(BuiltInRuleStateRepository::class);
    }
}
