<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/** @internal */
final class PlaygroundExecuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize Laravel's default {message, errors} validation response into
     * the playground's {error, error_class} JSON shape so the UI renders
     * every failure uniformly.
     */
    protected function failedValidation(Validator $validator): void
    {
        $first = $validator->errors()->first() ?: 'Invalid request payload.';

        throw new HttpResponseException(new JsonResponse([
            'error' => $first,
            'error_class' => 'InvalidPlaygroundRequest',
        ], 422));
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
