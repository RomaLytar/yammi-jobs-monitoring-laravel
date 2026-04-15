<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal Infrastructure detail. Mapped at the repository boundary.
 *
 * @property int $id
 * @property string $mutex
 * @property string $task_name
 * @property string $expression
 * @property ?string $timezone
 * @property string $status
 * @property \DateTimeImmutable $started_at
 * @property ?\DateTimeImmutable $finished_at
 * @property ?int $duration_ms
 * @property ?int $exit_code
 * @property ?string $output
 * @property ?string $exception
 * @property ?string $host
 */
final class ScheduledTaskRunModel extends Model
{
    protected $table = 'jobs_monitor_scheduled_runs';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
    ];
}
