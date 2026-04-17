<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

use Yammi\JobsMonitor\Application\DTO\TransferResultData;

/**
 * Copies every jobs_monitor_* table from one database connection to
 * another, optionally dropping the source tables and removing their
 * migration records afterwards. Application classes depend on this
 * port instead of Illuminate\Database directly.
 */
interface MonitorDataTransferrer
{
    public function transfer(string $from, string $to, bool $deleteSource): TransferResultData;
}
