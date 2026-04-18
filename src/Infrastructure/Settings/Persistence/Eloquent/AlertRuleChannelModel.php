<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

<<<<<<< HEAD
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobsMonitorModel;
=======
use Illuminate\Database\Eloquent\Model;
>>>>>>> origin/main

/**
 * @internal
 *
 * @property int $id
 * @property int $alert_rule_id
 * @property string $channel_name
 * @property int $position
 */
<<<<<<< HEAD
final class AlertRuleChannelModel extends JobsMonitorModel
=======
final class AlertRuleChannelModel extends Model
>>>>>>> origin/main
{
    public $timestamps = false;

    protected $table = 'jobs_monitor_alert_rule_channels';

<<<<<<< HEAD
    /** @var list<string> */
    protected $fillable = [
        'alert_rule_id',
        'channel_name',
        'position',
    ];
=======
    protected $guarded = [];
>>>>>>> origin/main

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'alert_rule_id' => 'integer',
        'position' => 'integer',
    ];
}
