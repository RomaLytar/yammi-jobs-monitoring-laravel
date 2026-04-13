<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;

final class InMemoryAlertSettingsRepository implements AlertSettingsRepository
{
    private ?AlertSettings $stored = null;

    public function get(): AlertSettings
    {
        return $this->stored ?? new AlertSettings(
            enabled: null,
            sourceName: null,
            monitorUrl: null,
            mailRecipients: new EmailRecipientList([]),
        );
    }

    public function save(AlertSettings $settings): void
    {
        $this->stored = $settings;
    }
}
