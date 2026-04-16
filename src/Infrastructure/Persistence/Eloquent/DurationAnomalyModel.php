<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 *
 * @property string $job_uuid
 * @property int $attempt
 * @property string $job_class
 * @property string $kind
 * @property int $duration_ms
 * @property int $baseline_p50_ms
 * @property int $baseline_p95_ms
 * @property int $samples_count
 * @property \DateTimeImmutable $detected_at
 */
final class DurationAnomalyModel extends Model
{
    protected $table = 'jobs_monitor_duration_anomalies';

    /** @var list<string> */
    protected $fillable = [
        'job_uuid',
        'attempt',
        'job_class',
        'kind',
        'duration_ms',
        'baseline_p50_ms',
        'baseline_p95_ms',
        'samples_count',
        'detected_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'attempt' => 'integer',
        'duration_ms' => 'integer',
        'baseline_p50_ms' => 'integer',
        'baseline_p95_ms' => 'integer',
        'samples_count' => 'integer',
        'detected_at' => 'immutable_datetime',
    ];
}
