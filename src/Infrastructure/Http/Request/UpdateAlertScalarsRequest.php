<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the alerts scalar form submission.
 *
 * Empty strings collapse to null so the action can clear DB overrides
 * (resolution then falls back to config or default).
 *
 * @internal
 */
final class UpdateAlertScalarsRequest extends FormRequest
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
            'source_name' => ['nullable', 'string', 'max:100'],
            'monitor_url' => ['nullable', 'string', 'max:500', 'url'],
        ];
    }

    public function sourceName(): ?string
    {
        return $this->normalizedString('source_name');
    }

    public function monitorUrl(): ?string
    {
        return $this->normalizedString('monitor_url');
    }

    private function normalizedString(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
