<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

final class TransferResultData
{
    public function __construct(
        public readonly int $rowsMoved,
        public readonly int $tablesProcessed,
    ) {}
}
