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
 * @property string $key
 * @property bool $enabled
 */
<<<<<<< HEAD
final class BuiltInRuleStateModel extends JobsMonitorModel
=======
final class BuiltInRuleStateModel extends Model
>>>>>>> origin/main
{
    public const CREATED_AT = null;

    protected $table = 'jobs_monitor_built_in_rule_state';

<<<<<<< HEAD
    /** @var list<string> */
    protected $fillable = [
        'key',
        'enabled',
    ];
=======
    protected $guarded = [];
>>>>>>> origin/main

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
    ];
}
