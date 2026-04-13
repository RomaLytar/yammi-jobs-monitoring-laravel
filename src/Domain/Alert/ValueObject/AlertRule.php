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
     * @param  int|null  $minAttempt  Only count job failures at this
     *                                attempt number or higher.
     *                                Used to silence first-try noise.
     */
    public function __construct(
        public readonly AlertTrigger $trigger,
        public readonly ?string $window,
        public readonly int $threshold,
        public readonly array $channels,
        public readonly int $cooldownMinutes,
        public readonly ?string $triggerValue = null,
        public readonly ?int $minAttempt = null,
    ) {
        $this->assertChannelsNotEmpty($channels);
        $this->assertThresholdPositive($threshold);
        $this->assertCooldownPositive($cooldownMinutes);
        $this->assertTriggerValueMatchesTrigger($trigger, $triggerValue);
        $this->assertMinAttemptValid($minAttempt);
        $this->windowSeconds = $this->resolveWindowSeconds($trigger, $window);
    }

    public function windowSeconds(): ?int
    {
        return $this->windowSeconds;
    }

    public function ruleKey(): string
    {
        return sprintf(
            '%s:%s:%s:%d:%s',
            $this->trigger->value,
            $this->triggerValue ?? '-',
            $this->window ?? '-',
            $this->threshold,
            $this->minAttempt ?? '-',
        );
    }

    /**
     * @param  list<string>  $channels
     */
    private function assertChannelsNotEmpty(array $channels): void
    {
        if ($channels !== []) {
            return;
        }

        throw new InvalidAlertRule('Alert rule must have at least one notification channel.');
    }

    private function assertThresholdPositive(int $threshold): void
    {
        if ($threshold > 0) {
            return;
        }

        throw new InvalidAlertRule(sprintf(
            'Alert rule threshold must be a positive integer, got %d.',
            $threshold,
        ));
    }

    private function assertCooldownPositive(int $cooldownMinutes): void
    {
        if ($cooldownMinutes > 0) {
            return;
        }

        throw new InvalidAlertRule(sprintf(
            'Alert rule cooldown_minutes must be a positive integer, got %d.',
            $cooldownMinutes,
        ));
    }

    private function assertMinAttemptValid(?int $minAttempt): void
    {
        if ($minAttempt === null || $minAttempt >= 1) {
            return;
        }

        throw new InvalidAlertRule(sprintf(
            'Alert rule min_attempt must be at least 1 when provided, got %d.',
            $minAttempt,
        ));
    }

    private function assertTriggerValueMatchesTrigger(AlertTrigger $trigger, ?string $triggerValue): void
    {
        $required = $trigger->requiresTriggerValue();
        $provided = $triggerValue !== null;

        if ($required === $provided) {
            return;
        }

        throw new InvalidAlertRule(sprintf(
            $required
                ? 'Alert rule trigger "%s" requires a "value" field.'
                : 'Alert rule trigger "%s" does not accept a "value" field.',
            $trigger->value,
        ));
    }

    private function resolveWindowSeconds(AlertTrigger $trigger, ?string $window): ?int
    {
        if (! $trigger->requiresWindow()) {
            return null;
        }

        if ($window === null) {
            throw new InvalidAlertRule(sprintf(
                'Alert rule trigger "%s" requires a "window" field.',
                $trigger->value,
            ));
        }

        return $this->parseWindow($window);
    }

    private function parseWindow(string $window): int
    {
        if (preg_match(self::WINDOW_REGEX, $window, $matches) !== 1) {
            throw $this->invalidWindow($window);
        }

        $amount = (int) $matches[1];
        if ($amount <= 0) {
            throw $this->invalidWindow($window);
        }

        return $amount * self::WINDOW_UNIT_SECONDS[$matches[2]];
    }

    private function invalidWindow(string $window): InvalidAlertRule
    {
        return new InvalidAlertRule(sprintf(
            'Alert rule window "%s" is not a valid duration. Expected format like "5m", "1h", "2d".',
            $window,
        ));
    }
}
