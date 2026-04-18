<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobsMonitorModel;

/**
 * @internal Eloquent representation of the singleton row in
 *           `jobs_monitor_alert_settings`.
 *
 * @property int $id
 * @property bool|null $enabled
 * @property string|null $source_name
 * @property string|null $monitor_url
 */
final class AlertSettingsModel extends JobsMonitorModel
{
    public const SINGLETON_ID = 1;

    protected $table = 'jobs_monitor_alert_settings';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'enabled',
        'source_name',
        'monitor_url',
    ];

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
