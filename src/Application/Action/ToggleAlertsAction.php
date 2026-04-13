<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;

/**
 * Sets the alert subsystem's enabled flag in the DB.
 *
 * Passing `null` clears the DB override and falls back to config (or
 * "off" if config is unset). Other settings (scalars, recipients) are
 * untouched.
 */
final class ToggleAlertsAction
{
    public function __construct(
        private readonly AlertSettingsRepository $repo,
    ) {}

    public function __invoke(?bool $enabled): void
    {
        $current = $this->repo->get();
        $this->repo->save($current->withEnabled($enabled));
    }
}
