<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 *
 * @property string $job_class
 * @property int $samples_count
 * @property int $p50_ms
 * @property int $p95_ms
 * @property int $min_ms
 * @property int $max_ms
 * @property \DateTimeImmutable $computed_over_from
 * @property \DateTimeImmutable $computed_over_to
 */
final class DurationBaselineModel extends Model
{
    protected $table = 'jobs_monitor_duration_baselines';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'samples_count' => 'integer',
        'p50_ms' => 'integer',
        'p95_ms' => 'integer',
        'min_ms' => 'integer',
        'max_ms' => 'integer',
        'computed_over_from' => 'immutable_datetime',
        'computed_over_to' => 'immutable_datetime',
    ];
}
