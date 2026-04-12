<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\Exception\InvalidAlertRule;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;

/**
 * Maps raw host config arrays into validated AlertRule value objects.
 *
 * Tolerant to common config footguns: env-originated numeric strings are
 * coerced, missing fields surface as domain exceptions with actionable
 * messages. Validation of value ranges stays in the VO constructor.
 */
final class AlertRuleFactory
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function fromArray(array $raw): AlertRule
    {
        return new AlertRule(
            trigger: $this->extractTrigger($raw),
            window: $this->extractNullableString($raw, 'window'),
            threshold: $this->extractRequiredInt($raw, 'threshold'),
            channels: $this->normalizeChannels($raw['channels'] ?? []),
            cooldownMinutes: $this->extractRequiredInt($raw, 'cooldown_minutes'),
            triggerValue: $this->extractNullableString($raw, 'value'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rawList
     * @return list<AlertRule>
     */
    public function fromList(array $rawList): array
    {
        return array_values(array_map(
            fn (array $raw): AlertRule => $this->fromArray($raw),
            $rawList,
        ));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractTrigger(array $raw): AlertTrigger
    {
        $rawTrigger = $raw['trigger'] ?? throw new InvalidAlertRule(
            'Alert rule is missing required field "trigger".',
        );

        return AlertTrigger::tryFrom((string) $rawTrigger)
            ?? throw new InvalidAlertRule(sprintf(
                'Unknown alert trigger "%s".',
                (string) $rawTrigger,
            ));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractRequiredInt(array $raw, string $key): int
    {
        // The VO constructor rejects non-positive values for these keys,
        // so missing = 0 surfaces as the same invariant violation as
        // the explicit case. Keeping parsing lenient here lets the
        // domain own the "must be positive" story.
        return (int) ($raw[$key] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractNullableString(array $raw, string $key): ?string
    {
        return isset($raw[$key]) ? (string) $raw[$key] : null;
    }

    /**
     * @param  mixed  $channels
     * @return list<string>
     */
    private function normalizeChannels($channels): array
    {
        if (! is_array($channels)) {
            return [];
        }

        return array_values(array_map(fn ($c) => (string) $c, $channels));
    }
}
