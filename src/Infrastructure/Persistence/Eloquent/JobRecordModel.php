<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent representation of a row in the `jobs_monitor` table.
 *
 * @internal Infrastructure detail. Never returned past the repository
 *           boundary; the repository maps it to a {@see \Yammi\JobsMonitor\Domain\Job\Entity\JobRecord}.
 *
 * @property string $uuid
 * @property string $job_class
 * @property string $connection
 * @property string $queue
 * @property string $status
 * @property int $attempt
 * @property \DateTimeImmutable $started_at
 * @property \DateTimeImmutable|null $finished_at
 * @property int|null $duration_ms
 * @property string|null $exception
 * @property array<string, mixed>|null $payload
 */
final class JobRecordModel extends Model
{
    protected $table = 'jobs_monitor';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
        'attempt' => 'integer',
        'duration_ms' => 'integer',
        'payload' => 'array',
    ];
}
