<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Application\Contract\MonitorDataTransferrer;
use Yammi\JobsMonitor\Application\DTO\TransferResultData;

/**
 * Application-level use case for moving monitor rows between two
 * configured database connections. Delegates the infrastructure-heavy
 * SQL + chunking to a MonitorDataTransferrer port so the Application
 * layer does not import Illuminate\Database.
 */
final class TransferMonitorDataAction
{
    public function __construct(
        private readonly MonitorDataTransferrer $transferrer,
    ) {}

    public function __invoke(string $from, string $to, bool $deleteSource): TransferResultData
    {
        return $this->transferrer->transfer($from, $to, $deleteSource);
    }
}
