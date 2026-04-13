<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a toggle/clear request for a built-in alert rule override.
 *
 * `enabled` is required and may be true, false, or null (which clears
 * the override and falls back to the shipped code default).
 *
 * @internal
 */
final class ToggleBuiltInRuleRequest extends FormRequest
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
            'enabled' => ['present', 'nullable', 'boolean'],
        ];
    }

    public function enabled(): ?bool
    {
        $value = $this->validated()['enabled'] ?? null;

        return $value === null ? null : (bool) $value;
    }
}
