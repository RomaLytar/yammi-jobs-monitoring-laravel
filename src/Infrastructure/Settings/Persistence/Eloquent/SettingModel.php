<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobsMonitorModel;

/** @internal */
final class SettingModel extends JobsMonitorModel
{
    protected $table = 'jobs_monitor_settings';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];
}
