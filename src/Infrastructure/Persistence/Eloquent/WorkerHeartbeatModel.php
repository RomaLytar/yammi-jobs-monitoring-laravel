<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

/**
 * @internal Infrastructure detail. Mapped at the repository boundary.
 *
 * @property int $id
 * @property string $worker_id
 * @property string $connection
 * @property string $queue
 * @property string $host
 * @property int $pid
 * @property \DateTimeImmutable $last_seen_at
 * @property ?\DateTimeImmutable $stopped_at
 */
final class WorkerHeartbeatModel extends JobsMonitorModel
{
    protected $table = 'jobs_monitor_worker_heartbeats';

    /** @var list<string> */
    protected $fillable = [
        'worker_id',
        'connection',
        'queue',
        'host',
        'pid',
        'last_seen_at',
        'stopped_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'pid' => 'integer',
        'last_seen_at' => 'immutable_datetime',
        'stopped_at' => 'immutable_datetime',
    ];
}
