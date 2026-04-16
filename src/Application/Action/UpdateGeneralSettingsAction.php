<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;

/** @internal */
final class UpdateGeneralSettingsAction
{
    public function __construct(
        private readonly GeneralSettingRepository $repo,
        private readonly SettingRegistry $registry,
    ) {}

    /**
     * @param  array<string, array<string, bool|int|float|string|null>>  $values  group => [key => value]
     */
    public function __invoke(array $values): void
    {
        foreach ($values as $group => $settings) {
            foreach ($settings as $key => $value) {
                $def = $this->registry->find($group, $key);

                if ($def === null) {
                    continue;
                }

                if ($value === null) {
                    $this->repo->remove($group, $key);

                    continue;
                }

                $this->repo->set(
                    $group,
                    $key,
                    $def->type->serialize($value),
                    $def->type->value,
                );
            }
        }
    }
}
