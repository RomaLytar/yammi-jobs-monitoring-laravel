<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;

/**
 * Snapshot of the alert subsystem's effective runtime configuration.
 *
 * Produced by AlertConfigResolver each evaluation tick. Carries the
 * already-merged ruleset (DB > config > built-in defaults), so consumers
 * never branch on source-of-truth concerns.
 */
final class AlertRuntimeConfig
{
    /**
     * @param  list<AlertRule>  $rules
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $rules,
    ) {}
}
