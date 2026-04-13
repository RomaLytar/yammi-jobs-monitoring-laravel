<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the alerts toggle endpoint payload.
 *
 * @internal
 */
final class ToggleAlertsRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }
}
