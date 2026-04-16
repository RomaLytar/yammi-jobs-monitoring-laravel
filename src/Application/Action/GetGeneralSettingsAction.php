<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Yammi\JobsMonitor\Application\DTO\ResolvedSettingData;
use Yammi\JobsMonitor\Application\DTO\SettingDefinitionData;
use Yammi\JobsMonitor\Application\DTO\SettingGroupData;
use Yammi\JobsMonitor\Application\DTO\SettingType;
use Yammi\JobsMonitor\Application\DTO\ValueSource;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;

/** @internal */
final class GetGeneralSettingsAction
{
    public function __construct(
        private readonly GeneralSettingRepository $repo,
        private readonly SettingRegistry $registry,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * @return list<SettingGroupData>
     */
    public function __invoke(): array
    {
        $dbValues = $this->repo->all();
        $result = [];

        foreach ($this->registry->groups() as $groupKey => $group) {
            $resolved = [];

            foreach ($group['settings'] as $def) {
                $resolved[] = $this->resolve($def, $dbValues[$groupKey][$def->key] ?? null);
            }

            $result[] = new SettingGroupData(
                key: $groupKey,
                label: $group['label'],
                description: $group['description'],
                icon: $group['icon'],
                settings: $resolved,
            );
        }

        return $result;
    }

    private function resolve(SettingDefinitionData $def, ?string $dbRaw): ResolvedSettingData
    {
        if ($dbRaw !== null) {
            return new ResolvedSettingData(
                group: $def->group,
                key: $def->key,
                label: $def->label,
                description: $def->description,
                type: $def->type,
                value: $def->type->cast($dbRaw),
                source: ValueSource::Db,
                default: $def->default,
                min: $def->min,
                max: $def->max,
                suffix: $def->suffix,
                options: $def->options,
                pattern: $def->pattern,
            );
        }

        $configValue = $this->config->get($def->configPath);

        if ($configValue !== null) {
            return new ResolvedSettingData(
                group: $def->group,
                key: $def->key,
                label: $def->label,
                description: $def->description,
                type: $def->type,
                value: $this->castConfig($def, $configValue),
                source: ValueSource::Config,
                default: $def->default,
                min: $def->min,
                max: $def->max,
                suffix: $def->suffix,
                options: $def->options,
                pattern: $def->pattern,
            );
        }

        return new ResolvedSettingData(
            group: $def->group,
            key: $def->key,
            label: $def->label,
            description: $def->description,
            type: $def->type,
            value: $def->default,
            source: ValueSource::Default,
            default: $def->default,
            min: $def->min,
            max: $def->max,
            suffix: $def->suffix,
            options: $def->options,
            pattern: $def->pattern,
        );
    }

    private function castConfig(SettingDefinitionData $def, mixed $configValue): bool|int|float|string
    {
        return match ($def->type) {
            SettingType::Boolean => (bool) $configValue,
            SettingType::Integer => (int) $configValue,
            SettingType::Float => (float) $configValue,
            SettingType::String => (string) $configValue,
        };
    }
}
