<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\ValueObject;

use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\Exception\InvalidAlertRule;

final class AlertRule
{
    private const WINDOW_REGEX = '/^(\d+)([smhd])$/';

    private const WINDOW_UNIT_SECONDS = [
        's' => 1,
        'm' => 60,
        'h' => 3600,
        'd' => 86400,
    ];

    private readonly ?int $windowSeconds;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly AlertTrigger $trigger,
        public readonly ?string $window,
        public readonly int $threshold,
        public readonly array $channels,
        public readonly int $cooldownMinutes,
        public readonly ?string $triggerValue = null,
    ) {
        if ($channels === []) {
            throw InvalidAlertRule::emptyChannels();
        }

        if ($threshold <= 0) {
            throw InvalidAlertRule::nonPositiveThreshold($threshold);
        }

        if ($cooldownMinutes <= 0) {
            throw InvalidAlertRule::nonPositiveCooldown($cooldownMinutes);
        }

        if ($trigger->requiresWindow()) {
            if ($window === null) {
                throw InvalidAlertRule::missingWindow($trigger->value);
            }

            $this->windowSeconds = $this->parseWindow($window);
        } else {
            $this->windowSeconds = null;
        }

        if ($trigger->requiresTriggerValue() && $triggerValue === null) {
            throw InvalidAlertRule::missingTriggerValue($trigger->value);
        }

        if (! $trigger->requiresTriggerValue() && $triggerValue !== null) {
            throw InvalidAlertRule::unexpectedTriggerValue($trigger->value);
        }
    }

    public function windowSeconds(): ?int
    {
        return $this->windowSeconds;
    }

    public function ruleKey(): string
    {
        return sprintf(
            '%s:%s:%s:%d',
            $this->trigger->value,
            $this->triggerValue ?? '-',
            $this->window ?? '-',
            $this->threshold,
        );
    }

    private function parseWindow(string $window): int
    {
        if (preg_match(self::WINDOW_REGEX, $window, $matches) !== 1) {
            throw InvalidAlertRule::invalidWindow($window);
        }

        $amount = (int) $matches[1];
        if ($amount <= 0) {
            throw InvalidAlertRule::invalidWindow($window);
        }

        return $amount * self::WINDOW_UNIT_SECONDS[$matches[2]];
    }
}
