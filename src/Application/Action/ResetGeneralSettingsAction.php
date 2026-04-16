<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;

/** @internal */
final class ResetGeneralSettingsAction
{
    public function __construct(
        private readonly GeneralSettingRepository $repo,
        private readonly SettingRegistry $registry,
    ) {}

    public function __invoke(): void
    {
        foreach ($this->registry->groups() as $groupKey => $group) {
            foreach ($group['settings'] as $def) {
                $this->repo->remove($groupKey, $def->key);
            }
        }
    }
}
