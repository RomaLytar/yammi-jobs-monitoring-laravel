<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\DTO\AlertSettingsData;
use Yammi\JobsMonitor\Application\DTO\ValueSource;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;

/**
 * Returns the resolved alert settings + per-field source markers.
 *
 * Resolution per field: DB row > explicit config value > auto-derived
 * default > "feature off"/empty.
 *
 * Source markers let the UI distinguish "user has edited this" from
 * "still inheriting config" from "we figured this out ourselves" from
 * "nothing configured anywhere".
 */
final class GetAlertSettingsAction
{
    /**
     * @param  list<string>  $configRecipients
     */
    public function __construct(
        private readonly AlertSettingsRepository $repo,
        private readonly ?bool $configEnabled,
        private readonly ?string $configSourceName,
        private readonly ?string $autoSourceName,
        private readonly ?string $configMonitorUrl,
        private readonly ?string $autoMonitorUrl,
        private readonly array $configRecipients,
    ) {}

    public function __invoke(): AlertSettingsData
    {
        $db = $this->repo->get();

        [$enabled, $enabledSource] = $this->resolveEnabled($db->isEnabled());
        [$sourceName, $sourceNameSource] = $this->resolveScalar(
            dbValue: $db->sourceName(),
            configValue: $this->normalize($this->configSourceName),
            autoValue: $this->normalize($this->autoSourceName),
        );
        [$monitorUrl, $monitorUrlSource] = $this->resolveScalar(
            dbValue: $db->monitorUrl()?->toString(),
            configValue: $this->normalize($this->configMonitorUrl),
            autoValue: $this->normalize($this->autoMonitorUrl),
        );
        [$recipients, $recipientsSource] = $this->resolveRecipients($db->mailRecipients()->toArray());

        return new AlertSettingsData(
            enabled: $enabled,
            enabledSource: $enabledSource,
            sourceName: $sourceName,
            sourceNameSource: $sourceNameSource,
            monitorUrl: $monitorUrl,
            monitorUrlSource: $monitorUrlSource,
            recipients: $recipients,
            recipientsSource: $recipientsSource,
        );
    }

    /**
     * @return array{0: bool, 1: ValueSource}
     */
    private function resolveEnabled(?bool $dbValue): array
    {
        if ($dbValue !== null) {
            return [$dbValue, ValueSource::Db];
        }

        if ($this->configEnabled !== null) {
            return [$this->configEnabled, ValueSource::Config];
        }

        return [false, ValueSource::Default];
    }

    /**
     * @return array{0: ?string, 1: ValueSource}
     */
    private function resolveScalar(?string $dbValue, ?string $configValue, ?string $autoValue): array
    {
        if ($dbValue !== null) {
            return [$dbValue, ValueSource::Db];
        }

        if ($configValue !== null) {
            return [$configValue, ValueSource::Config];
        }

        if ($autoValue !== null) {
            return [$autoValue, ValueSource::Auto];
        }

        return [null, ValueSource::Default];
    }

    /**
     * @param  list<string>  $dbRecipients
     * @return array{0: list<string>, 1: ValueSource}
     */
    private function resolveRecipients(array $dbRecipients): array
    {
        if ($dbRecipients !== []) {
            return [$dbRecipients, ValueSource::Db];
        }

        if ($this->configRecipients !== []) {
            return [array_values($this->configRecipients), ValueSource::Config];
        }

        return [[], ValueSource::Default];
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
