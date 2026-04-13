<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;

/**
 * Validates field-level edits to a built-in alert rule.
 *
 * The form only exposes fields that make sense to tune (threshold,
 * window, cooldown, min_attempt, channels, enabled). Key, trigger and
 * trigger_value are fixed by the built-in and never edited via UI —
 * they come from the route parameter and the shipped catalog.
 *
 * @internal
 */
final class SaveBuiltInRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'threshold' => ['required', 'integer', 'min:1'],
            'cooldown_minutes' => ['required', 'integer', 'min:1'],
            'window' => ['nullable', 'string', 'max:16', 'regex:/^\d+[smhd]$/'],
            'min_attempt' => ['nullable', 'integer', 'min:1'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(['slack', 'mail'])],
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    public function buildAlertRule(): AlertRule
    {
        $validated = $this->validated();
        $key = (string) $this->route('key');
        $defaults = $this->builtInDefaults($key);

        $trigger = AlertTrigger::from((string) $defaults['trigger']);

        return new AlertRule(
            trigger: $trigger,
            window: $this->nullableString($validated['window'] ?? null),
            threshold: (int) $validated['threshold'],
            channels: array_values((array) $validated['channels']),
            cooldownMinutes: (int) $validated['cooldown_minutes'],
            triggerValue: isset($defaults['value']) ? (string) $defaults['value'] : null,
            minAttempt: $this->nullableInt($validated['min_attempt'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function builtInDefaults(string $key): array
    {
        /** @var \Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider $provider */
        $provider = app(\Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider::class);

        return $provider->catalog()[$key] ?? [];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
