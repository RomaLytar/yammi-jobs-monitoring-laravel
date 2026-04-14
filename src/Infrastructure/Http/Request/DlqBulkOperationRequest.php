<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Validates a bulk DLQ action (retry or delete).
 *
 * The ID list is capped at {@see self::MAX_IDS} so a single request cannot
 * trigger an unbounded number of retries/deletes. The UI is expected to
 * split larger selections into sequential chunks of this size.
 *
 * @internal
 */
final class DlqBulkOperationRequest extends FormRequest
{
    public const MAX_IDS = 100;

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
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return list<JobIdentifier>
     */
    public function identifiers(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated()['ids'];

        return array_values(array_map(
            static fn (string $id): JobIdentifier => new JobIdentifier($id),
            $ids,
        ));
    }
}
