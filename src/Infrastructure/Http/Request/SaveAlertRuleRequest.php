<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Entity\ManagedAlertRule;

/**
 * Validates the alert-rule create/update payload.
 *
 * Used by both web (form submit) and API (JSON body) endpoints.
 *
 * @internal
 */
final class SaveAlertRuleRequest extends FormRequest
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
            'key' => ['required', 'string', 'min:1', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'trigger' => ['required', 'string', Rule::in($this->triggerValues())],
            'trigger_value' => ['nullable', 'string', 'max:255'],
            'window' => ['nullable', 'string', 'max:16', 'regex:/^\d+[smhd]$/'],
            'threshold' => ['required', 'integer', 'min:1'],
            'cooldown_minutes' => ['required', 'integer', 'min:1'],
            'min_attempt' => ['nullable', 'integer', 'min:1'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(['slack', 'mail'])],
            'enabled' => ['required', 'boolean'],
            'overrides_built_in' => ['nullable', 'string', Rule::in($this->builtInKeys())],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function buildEntity(?int $id = null): ManagedAlertRule
    {
        $validated = $this->validated();

        $trigger = AlertTrigger::from((string) $validated['trigger']);

        return new ManagedAlertRule(
            id: $id,
            key: (string) $validated['key'],
            rule: new AlertRule(
                trigger: $trigger,
                window: $this->stringOrNull($validated['window'] ?? null),
                threshold: (int) $validated['threshold'],
                channels: array_values((array) $validated['channels']),
                cooldownMinutes: (int) $validated['cooldown_minutes'],
                triggerValue: $this->stringOrNull($validated['trigger_value'] ?? null),
                minAttempt: $this->intOrNull($validated['min_attempt'] ?? null),
            ),
            enabled: (bool) $validated['enabled'],
            overridesBuiltIn: $this->stringOrNull($validated['overrides_built_in'] ?? null),
            position: (int) ($validated['position'] ?? 0),
        );
    }

    /**
     * @return list<string>
     */
    private function triggerValues(): array
    {
        return array_map(static fn (AlertTrigger $t) => $t->value, AlertTrigger::cases());
    }

    /**
     * @return list<string>
     */
    private function builtInKeys(): array
    {
        $provider = app(BuiltInRulesProvider::class);

        return array_keys($provider->catalog());
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
