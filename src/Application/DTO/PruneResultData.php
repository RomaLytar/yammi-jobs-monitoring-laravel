<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

/**
 * Outcome of a prune run: how many rows were deleted per dataset.
 */
final class PruneResultData
{
    /**
     * @param  array<string, int>  $deletedByDataset  dataset name => deleted row count
     */
    public function __construct(
        public readonly array $deletedByDataset,
    ) {}

    public function total(): int
    {
        return array_sum($this->deletedByDataset);
    }
}
