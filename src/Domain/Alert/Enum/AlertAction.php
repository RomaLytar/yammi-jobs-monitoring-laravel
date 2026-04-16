<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\Enum;

/**
 * Trigger = the rule just tripped (or is still tripped).
 * Resolve = the condition that tripped the rule has cleared — emit a
 * closing event so incident-management channels (PagerDuty, Opsgenie)
 * auto-close the open incident.
 */
enum AlertAction: string
{
    case Trigger = 'trigger';
    case Resolve = 'resolve';

    public function isResolve(): bool
    {
        return $this === self::Resolve;
    }
}
