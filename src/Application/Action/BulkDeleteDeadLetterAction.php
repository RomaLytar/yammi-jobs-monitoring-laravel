<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Remove many dead-letter entries in one request.
 *
 * Each identifier maps to a separate repository call; any that match no
 * stored attempts are treated as a per-item failure (surfaced to the UI)
 * rather than raising. This matches the single-delete path where a
 * missing record is a user-facing error, not a crash.
 */
final class BulkDeleteDeadLetterAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
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
            $deleted = $this->repository->deleteByIdentifier($id);

            if ($deleted > 0) {
                $succeeded++;

                continue;
            }

            $failed++;
            $errors[$id->value] = 'Dead-letter entry not found.';
        }

        return new BulkOperationResult($succeeded, $failed, $errors);
    }
}
