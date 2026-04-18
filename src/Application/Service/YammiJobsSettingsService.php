<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

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
use Yammi\JobsMonitor\Application\DTO\AlertRulesOverviewData;
use Yammi\JobsMonitor\Application\DTO\AlertSettingsData;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;

/**
 * Programmatic settings surface behind the YammiJobsSettings facade.
 * Wraps the existing settings Actions and managed-rule repository so
 * host apps can read and mutate alerts/general/rules without driving
 * the HTTP layer.
 */
final class YammiJobsSettingsService
{
    public function __construct(
        private readonly GetGeneralSettingsAction $getGeneral,
        private readonly UpdateGeneralSettingsAction $updateGeneral,
        private readonly ResetGeneralSettingsAction $resetGeneral,
        private readonly GetAlertSettingsAction $getAlerts,
        private readonly ToggleAlertsAction $toggleAlerts,
        private readonly UpdateAlertScalarSettingsAction $updateAlertScalars,
        private readonly AddAlertRecipientsAction $addRecipients,
        private readonly RemoveAlertRecipientAction $removeRecipient,
        private readonly ListAlertRulesAction $listRules,
        private readonly SaveManagedAlertRuleAction $saveRule,
        private readonly DeleteManagedAlertRuleAction $deleteRule,
        private readonly ToggleBuiltInRuleAction $toggleBuiltIn,
        private readonly UpdateBuiltInRuleAction $updateBuiltIn,
        private readonly ResetBuiltInRuleAction $resetBuiltIn,
        private readonly ManagedAlertRuleRepository $rules,
    ) {}

    /**
     * @return list<\Yammi\JobsMonitor\Application\DTO\SettingGroupData>
     */
    public function general(): array
    {
        return ($this->getGeneral)();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateGeneral(array $values): void
    {
        ($this->updateGeneral)($values);
    }

    public function resetGeneral(): void
    {
        ($this->resetGeneral)();
    }

    public function alerts(): AlertSettingsData
    {
        return ($this->getAlerts)();
    }

    public function toggleAlerts(?bool $enabled): void
    {
        ($this->toggleAlerts)($enabled);
    }

    public function updateAlerts(?string $sourceName, ?MonitorUrl $monitorUrl): void
    {
        ($this->updateAlertScalars)($sourceName, $monitorUrl);
    }

    /**
     * @param  list<string>  $emails
     */
    public function addAlertRecipients(array $emails): void
    {
        ($this->addRecipients)($emails);
    }

    public function removeAlertRecipient(string $email): void
    {
        ($this->removeRecipient)($email);
    }

    public function rules(): AlertRulesOverviewData
    {
        return ($this->listRules)();
    }

    public function rule(int $id): ?ManagedAlertRule
    {
        return $this->rules->findById($id);
    }

    public function ruleByKey(string $key): ?ManagedAlertRule
    {
        return $this->rules->findByKey($key);
    }

    public function saveRule(ManagedAlertRule $rule): ManagedAlertRule
    {
        return ($this->saveRule)($rule);
    }

    public function deleteRule(int $id): bool
    {
        return ($this->deleteRule)($id);
    }

    public function toggleBuiltInRule(string $key, ?bool $enabled): void
    {
        ($this->toggleBuiltIn)($key, $enabled);
    }

    public function updateBuiltInRule(string $key, AlertRule $rule, bool $enabled): ManagedAlertRule
    {
        return ($this->updateBuiltIn)($key, $rule, $enabled);
    }

    public function resetBuiltInRule(string $key): void
    {
        ($this->resetBuiltIn)($key);
    }
}
