<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Facade;

use Illuminate\Support\Facades\Facade;
use Yammi\JobsMonitor\Application\DTO\AlertRulesOverviewData;
use Yammi\JobsMonitor\Application\DTO\AlertSettingsData;
use Yammi\JobsMonitor\Application\DTO\SettingGroupData;
use Yammi\JobsMonitor\Application\Service\YammiJobsSettingsService;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;

/**
 * Public settings facade — read/update general settings, alert settings,
 * managed alert rules and built-in rule overrides.
 *
 * @method static list<SettingGroupData> general()
 * @method static void updateGeneral(array $values)
 * @method static void resetGeneral()
 * @method static AlertSettingsData alerts()
 * @method static void toggleAlerts(?bool $enabled)
 * @method static void updateAlerts(?string $sourceName, ?MonitorUrl $monitorUrl)
 * @method static void addAlertRecipients(array $emails)
 * @method static void removeAlertRecipient(string $email)
 * @method static AlertRulesOverviewData rules()
 * @method static ?ManagedAlertRule rule(int $id)
 * @method static ?ManagedAlertRule ruleByKey(string $key)
 * @method static ManagedAlertRule saveRule(ManagedAlertRule $rule)
 * @method static bool deleteRule(int $id)
 * @method static void toggleBuiltInRule(string $key, ?bool $enabled)
 * @method static ManagedAlertRule updateBuiltInRule(string $key, AlertRule $rule, bool $enabled)
 * @method static void resetBuiltInRule(string $key)
 *
 * @see YammiJobsSettingsService
 */
final class YammiJobsSettings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return YammiJobsSettingsService::class;
    }
}
