<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/** @internal */
final class PlaygroundExecuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z]+::[A-Za-z][A-Za-z0-9]*$/'],
            'args' => ['sometimes', 'array'],
        ];
    }

    public function methodKey(): string
    {
        return (string) $this->input('method');
    }

    /**
     * @return array<string, mixed>
     */
    public function args(): array
    {
        $args = $this->input('args');

        return is_array($args) ? $args : [];
    }
}
