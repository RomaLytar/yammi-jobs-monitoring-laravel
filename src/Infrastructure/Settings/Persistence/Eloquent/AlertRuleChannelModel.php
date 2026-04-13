<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 *
 * @property int $id
 * @property int $alert_rule_id
 * @property string $channel_name
 * @property int $position
 */
final class AlertRuleChannelModel extends Model
{
    public $timestamps = false;

    protected $table = 'jobs_monitor_alert_rule_channels';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'alert_rule_id' => 'integer',
        'position' => 'integer',
    ];
}
