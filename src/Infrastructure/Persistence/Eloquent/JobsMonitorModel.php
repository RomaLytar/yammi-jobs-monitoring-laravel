<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** @internal */
abstract class JobsMonitorModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('jobs-monitor.database.connection');
    }
}
