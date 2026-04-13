<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one or more email recipients in a single request.
 *
 * Accepts either:
 *  - `emails`: a JSON array (preferred for API clients), OR
 *  - `email`:  a comma- or newline-separated string (web textarea UX).
 *
 * Format/uniqueness checks happen here AND again in EmailRecipientList VO;
 * any exception from the VO bubbles to the controller.
 *
 * @internal
 */
final class AddAlertRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['emails' => $this->parsedEmails()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['required', 'string', 'email', 'max:254', Rule::notIn(['', null])],
        ];
    }

    /**
     * @return list<string>
     */
    public function emails(): array
    {
        /** @var list<string> $values */
        $values = $this->validated()['emails'];

        return array_values($values);
    }

    /**
     * @return list<string>
     */
    private function parsedEmails(): array
    {
        $raw = $this->input('emails', $this->input('email'));

        if (is_array($raw)) {
            return array_values(array_filter(
                array_map(static fn ($v): string => is_string($v) ? trim($v) : '', $raw),
                static fn (string $v): bool => $v !== '',
            ));
        }

        if (! is_string($raw)) {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $v): string => trim($v), $parts),
            static fn (string $v): bool => $v !== '',
        ));
    }
}
