<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;

/**
 * Persists the source-name and monitor-url scalar settings.
 *
 * Enabled flag and recipients are intentionally untouched — they have
 * their own dedicated actions (ToggleAlertsAction, Add/RemoveAlertRecipient).
 *
 * Each parameter is a full replacement: passing `null` clears the DB
 * override for that field, restoring the config-or-default fallback.
 */
final class UpdateAlertScalarSettingsAction
{
    public function __construct(
        private readonly AlertSettingsRepository $repo,
    ) {}

    public function __invoke(?string $sourceName, ?MonitorUrl $monitorUrl): void
    {
        $current = $this->repo->get();

        $updated = $current
            ->withSourceName($sourceName)
            ->withMonitorUrl($monitorUrl);

        $this->repo->save($updated);
    }
}
