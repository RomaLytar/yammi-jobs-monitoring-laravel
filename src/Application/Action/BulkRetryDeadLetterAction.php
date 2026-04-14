<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use RuntimeException;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Re-dispatch many dead-letter jobs in one request.
 *
 * Delegates each identifier to {@see RetryDeadLetterJobAction} so behavior
 * stays identical to the single-item path. Per-item failures are captured
 * in the returned result and never abort the remaining items — the UI
 * decides how to report partial failures to the operator.
 *
 * The HTTP layer caps the input size; this action processes the list
 * sequentially without holding extra state, so memory is bounded by the
 * caller.
 */
final class BulkRetryDeadLetterAction
{
    public function __construct(
        private readonly RetryDeadLetterJobAction $retry,
    ) {}

    /**
     * @param  list<JobIdentifier>  $ids
     */
    public function __invoke(array $ids): BulkOperationResult
    {
        $succeeded = 0;
        $failed = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                ($this->retry)($id);
                $succeeded++;
            } catch (RuntimeException $e) {
                $failed++;
                $errors[$id->value] = $e->getMessage();
            }
        }

        return new BulkOperationResult($succeeded, $failed, $errors);
    }
}
