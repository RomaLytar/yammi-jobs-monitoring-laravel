<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

<<<<<<< HEAD
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobsMonitorModel;
=======
use Illuminate\Database\Eloquent\Model;
>>>>>>> origin/main

/**
 * @internal Eloquent representation of the singleton row in
 *           `jobs_monitor_alert_settings`.
 *
 * @property int $id
 * @property bool|null $enabled
 * @property string|null $source_name
 * @property string|null $monitor_url
 */
<<<<<<< HEAD
final class AlertSettingsModel extends JobsMonitorModel
=======
final class AlertSettingsModel extends Model
>>>>>>> origin/main
{
    public const SINGLETON_ID = 1;

    protected $table = 'jobs_monitor_alert_settings';

<<<<<<< HEAD
    /** @var list<string> */
    protected $fillable = [
        'id',
        'enabled',
        'source_name',
        'monitor_url',
    ];
=======
    protected $guarded = [];
>>>>>>> origin/main

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'enabled' => 'boolean',
    ];
}
