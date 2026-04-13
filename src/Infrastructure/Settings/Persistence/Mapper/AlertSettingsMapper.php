<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Mapper;

use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent\AlertSettingsModel;

final class AlertSettingsMapper
{
    /**
     * @param  list<string>  $recipients
     */
    public function toAggregate(?AlertSettingsModel $row, array $recipients): AlertSettings
    {
        return new AlertSettings(
            enabled: $row?->enabled,
            sourceName: $this->stringOrNull($row?->source_name),
            monitorUrl: $this->mapMonitorUrl($row?->monitor_url),
            mailRecipients: new EmailRecipientList($recipients),
        );
    }

    /**
     * @return array{enabled: bool|null, source_name: string|null, monitor_url: string|null}
     */
    public function toScalarRow(AlertSettings $settings): array
    {
        return [
            'enabled' => $settings->isEnabled(),
            'source_name' => $settings->sourceName(),
            'monitor_url' => $settings->monitorUrl()?->toString(),
        ];
    }

    private function stringOrNull(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : $value;
    }

    private function mapMonitorUrl(?string $value): ?MonitorUrl
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new MonitorUrl($value);
    }
}
