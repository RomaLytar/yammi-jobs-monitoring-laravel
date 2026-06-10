<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Throwable;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;

/**
 * Overlays operator-saved settings onto config at boot: DB value → config → default.
 *
 * @internal
 */
final class StoredSettingsApplier
{
    public function __construct(
        private readonly SettingRegistry $registry,
        private readonly GeneralSettingRepository $settings,
        private readonly ConfigRepository $config,
    ) {}

    public function apply(): void
    {
        try {
            $stored = $this->settings->all();
        } catch (Throwable) {
            return;
        }

        foreach ($this->registry->groups() as $groupKey => $group) {
            foreach ($group['settings'] as $definition) {
                $raw = $stored[$groupKey][$definition->key] ?? null;

                if ($raw !== null) {
                    $this->config->set($definition->configPath, $definition->type->cast($raw));
                }
            }
        }
    }
}
