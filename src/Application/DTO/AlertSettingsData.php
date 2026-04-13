<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

/**
 * Resolved snapshot of alert settings for the UI/API.
 *
 * Each scalar field is paired with its source marker (db/config/default)
 * so the view can render provenance badges. Recipients are flattened to
 * a list of strings — the source marker covers the whole list.
 */
final class AlertSettingsData
{
    /**
     * @param  list<string>  $recipients
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly ValueSource $enabledSource,
        public readonly ?string $sourceName,
        public readonly ValueSource $sourceNameSource,
        public readonly ?string $monitorUrl,
        public readonly ValueSource $monitorUrlSource,
        public readonly array $recipients,
        public readonly ValueSource $recipientsSource,
    ) {}
}
