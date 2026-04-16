<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 *
 * @property int $id
 * @property string $key
 * @property bool $enabled
 */
final class BuiltInRuleStateModel extends Model
{
    public const CREATED_AT = null;

    protected $table = 'jobs_monitor_built_in_rule_state';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'enabled',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
    ];
}
