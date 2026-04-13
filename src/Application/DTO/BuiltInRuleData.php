<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;

/**
 * Read-only descriptor of a shipped built-in alert rule for the UI/API.
 *
 * Field values reflect the **effective** state — if the user saved
 * overrides via UI those are surfaced here, otherwise the shipped code
 * defaults are returned. `hasOverride` tells the UI whether a "Reset"
 * action should be offered; `overrideRuleId` points at the managed
 * rule backing the override (used by API clients).
 */
final class BuiltInRuleData
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly string $key,
        public readonly AlertTrigger $trigger,
        public readonly ?string $triggerValue,
        public readonly ?string $window,
        public readonly int $threshold,
        public readonly int $cooldownMinutes,
        public readonly ?int $minAttempt,
        public readonly array $channels,
        public readonly bool $codeDefaultEnabled,
        public readonly bool $effectivelyEnabled,
        public readonly bool $hasOverride,
        public readonly ?int $overrideRuleId,
    ) {}
}
