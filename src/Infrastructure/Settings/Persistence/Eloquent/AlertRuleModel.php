<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @internal
 *
 * @property int $id
 * @property string $key
 * @property string $trigger
 * @property string|null $window
 * @property int $threshold
 * @property int $cooldown_minutes
 * @property int|null $min_attempt
 * @property string|null $trigger_value
 * @property bool $enabled
 * @property string|null $overrides_built_in
 * @property int $position
 */
final class AlertRuleModel extends Model
{
    protected $table = 'jobs_monitor_alert_rules';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'threshold' => 'integer',
        'cooldown_minutes' => 'integer',
        'min_attempt' => 'integer',
        'enabled' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * @return HasMany<AlertRuleChannelModel>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(AlertRuleChannelModel::class, 'alert_rule_id')
            ->orderBy('position');
    }
}
