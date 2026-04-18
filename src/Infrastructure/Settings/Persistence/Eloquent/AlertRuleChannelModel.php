<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobsMonitorModel;

/**
 * @internal
 *
 * @property int $id
 * @property int $alert_rule_id
 * @property string $channel_name
 * @property int $position
 */
final class AlertRuleChannelModel extends JobsMonitorModel
{
    public $timestamps = false;

    protected $table = 'jobs_monitor_alert_rule_channels';

    /** @var list<string> */
    protected $fillable = [
        'alert_rule_id',
        'channel_name',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'alert_rule_id' => 'integer',
        'position' => 'integer',
    ];
}
