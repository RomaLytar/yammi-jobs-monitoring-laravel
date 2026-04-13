<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Mapper;

use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\AlertRuleModel;

final class ManagedAlertRuleMapper
{
    /**
     * @param  list<string>  $channels
     */
    public function toEntity(AlertRuleModel $row, array $channels): ManagedAlertRule
    {
        return new ManagedAlertRule(
            id: (int) $row->id,
            key: (string) $row->key,
            rule: new AlertRule(
                trigger: AlertTrigger::from((string) $row->trigger),
                window: $row->window,
                threshold: (int) $row->threshold,
                channels: $channels,
                cooldownMinutes: (int) $row->cooldown_minutes,
                triggerValue: $row->trigger_value,
                minAttempt: $row->min_attempt,
            ),
            enabled: (bool) $row->enabled,
            overridesBuiltIn: $row->overrides_built_in,
            position: (int) $row->position,
        );
    }

    /**
     * @return array{
     *     key: string,
     *     trigger: string,
     *     window: string|null,
     *     threshold: int,
     *     cooldown_minutes: int,
     *     min_attempt: int|null,
     *     trigger_value: string|null,
     *     enabled: bool,
     *     overrides_built_in: string|null,
     *     position: int
     * }
     */
    public function toRow(ManagedAlertRule $rule): array
    {
        $alert = $rule->rule();

        return [
            'key' => $rule->key(),
            'trigger' => $alert->trigger->value,
            'window' => $alert->window,
            'threshold' => $alert->threshold,
            'cooldown_minutes' => $alert->cooldownMinutes,
            'min_attempt' => $alert->minAttempt,
            'trigger_value' => $alert->triggerValue,
            'enabled' => $rule->isEnabled(),
            'overrides_built_in' => $rule->overridesBuiltIn(),
            'position' => $rule->position(),
        ];
    }
}
