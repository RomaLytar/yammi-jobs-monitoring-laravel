<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

/**
 * Outcome of a bulk DLQ operation (retry or delete).
 *
 * Errors are keyed by the target job UUID so the UI can correlate failures
 * with the rows the user selected. Each bulk request processes a bounded
 * number of IDs, so this map stays small.
 */
final class BulkOperationResult
{
    /**
     * @param  array<string, string>  $errors  uuid => error message
     */
    public function __construct(
        public readonly int $succeeded,
        public readonly int $failed,
        public readonly array $errors,
    ) {}

    public function total(): int
    {
        return $this->succeeded + $this->failed;
    }
}
