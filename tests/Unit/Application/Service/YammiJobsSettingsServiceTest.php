<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use Illuminate\Config\Repository as ConfigRepository;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\AddAlertRecipientsAction;
use Yammi\JobsMonitor\Application\Action\DeleteManagedAlertRuleAction;
use Yammi\JobsMonitor\Application\Action\GetAlertSettingsAction;
use Yammi\JobsMonitor\Application\Action\GetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\ListAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\RemoveAlertRecipientAction;
use Yammi\JobsMonitor\Application\Action\ResetBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\ResetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\SaveManagedAlertRuleAction;
use Yammi\JobsMonitor\Application\Action\ToggleAlertsAction;
use Yammi\JobsMonitor\Application\Action\ToggleBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\UpdateAlertScalarSettingsAction;
use Yammi\JobsMonitor\Application\Action\UpdateBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\UpdateGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Application\Service\YammiJobsSettingsService;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryGeneralSettingRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;

final class YammiJobsSettingsServiceTest extends TestCase
{
    private InMemoryGeneralSettingRepository $general;

    private InMemoryAlertSettingsRepository $alerts;

    private InMemoryManagedAlertRuleRepository $rules;

    private InMemoryBuiltInRuleStateRepository $builtInState;

    private YammiJobsSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->general = new InMemoryGeneralSettingRepository;
        $this->alerts = new InMemoryAlertSettingsRepository;
        $this->rules = new InMemoryManagedAlertRuleRepository;
        $this->builtInState = new InMemoryBuiltInRuleStateRepository;

        $registry = new SettingRegistry;
        $config = new ConfigRepository;

        $this->service = new YammiJobsSettingsService(
            getGeneral: new GetGeneralSettingsAction($this->general, $registry, $config),
            updateGeneral: new UpdateGeneralSettingsAction($this->general, $registry),
            resetGeneral: new ResetGeneralSettingsAction($this->general, $registry),
            getAlerts: new GetAlertSettingsAction(
                $this->alerts,
                configEnabled: null,
                configSourceName: null,
                autoSourceName: null,
                configMonitorUrl: null,
                autoMonitorUrl: null,
                configRecipients: [],
            ),
            toggleAlerts: new ToggleAlertsAction($this->alerts),
            updateAlertScalars: new UpdateAlertScalarSettingsAction($this->alerts),
            addRecipients: new AddAlertRecipientsAction($this->alerts),
            removeRecipient: new RemoveAlertRecipientAction($this->alerts),
            listRules: new ListAlertRulesAction(
                builtInProvider: new BuiltInRulesProvider(new AlertRuleFactory),
                builtInState: $this->builtInState,
                rulesRepo: $this->rules,
            ),
            saveRule: new SaveManagedAlertRuleAction($this->rules),
            deleteRule: new DeleteManagedAlertRuleAction($this->rules),
            toggleBuiltIn: new ToggleBuiltInRuleAction(
                $this->builtInState,
                $this->rules,
            ),
            updateBuiltIn: new UpdateBuiltInRuleAction($this->rules),
            resetBuiltIn: new ResetBuiltInRuleAction($this->rules, $this->builtInState),
            rules: $this->rules,
        );
    }

    public function test_general_update_and_read_roundtrip(): void
    {
        $this->service->updateGeneral(['general' => ['retention_days' => 14]]);

        $all = $this->service->general();

        self::assertNotEmpty($all);
    }

    public function test_reset_general_clears_db_values(): void
    {
        $this->service->updateGeneral(['general' => ['retention_days' => 14]]);
        $this->service->resetGeneral();

        self::assertNull($this->general->get('general', 'retention_days'));
    }

    public function test_alerts_toggle_updates_repo(): void
    {
        $this->service->toggleAlerts(true);

        self::assertTrue($this->alerts->get()->isEnabled());
    }

    public function test_alerts_returns_data_dto(): void
    {
        $data = $this->service->alerts();

        self::assertFalse($data->enabled);
    }

    public function test_add_and_remove_recipient(): void
    {
        $this->service->addAlertRecipients(['ops@example.com', 'sre@example.com']);

        self::assertSame(
            ['ops@example.com', 'sre@example.com'],
            $this->alerts->get()->mailRecipients()->toArray(),
        );

        $this->service->removeAlertRecipient('ops@example.com');

        self::assertSame(['sre@example.com'], $this->alerts->get()->mailRecipients()->toArray());
    }

    public function test_rules_returns_overview(): void
    {
        $overview = $this->service->rules();

        self::assertNotEmpty($overview->builtInRules);
    }

    public function test_save_and_delete_rule(): void
    {
        $saved = $this->service->saveRule(new ManagedAlertRule(
            id: null,
            key: 'my_rule',
            rule: new AlertRule(
                trigger: AlertTrigger::FailureRate,
                window: '5m',
                threshold: 50,
                channels: ['slack'],
                cooldownMinutes: 10,
            ),
            enabled: true,
            overridesBuiltIn: null,
            position: 0,
        ));

        self::assertNotNull($saved->id());

        self::assertTrue($this->service->deleteRule($saved->id()));
        self::assertNull($this->service->rule($saved->id()));
    }

    public function test_toggle_built_in_rule(): void
    {
        $this->service->toggleBuiltInRule('critical_failure', false);

        self::assertFalse($this->builtInState->findEnabled('critical_failure'));
    }

    public function test_reset_built_in_rule_clears_state(): void
    {
        $this->service->toggleBuiltInRule('critical_failure', false);
        $this->service->resetBuiltInRule('critical_failure');

        self::assertNull($this->builtInState->findEnabled('critical_failure'));
    }
}
