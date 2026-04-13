<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\Repository;

use Yammi\JobsMonitor\Domain\Settings\Aggregate\AlertSettings;

/**
 * Persistence boundary for the AlertSettings singleton aggregate.
 *
 * Implementations MUST treat AlertSettings as a single logical record
 * (one tenant of settings per host application). save() upserts;
 * get() always returns an aggregate, with null fields and an empty
 * recipient list when nothing has been configured yet.
 */
interface AlertSettingsRepository
{
    public function get(): AlertSettings;

    public function save(AlertSettings $settings): void;
}
