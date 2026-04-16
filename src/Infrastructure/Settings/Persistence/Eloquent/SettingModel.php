<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final class SettingModel extends Model
{
    protected $table = 'jobs_monitor_settings';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];
}
