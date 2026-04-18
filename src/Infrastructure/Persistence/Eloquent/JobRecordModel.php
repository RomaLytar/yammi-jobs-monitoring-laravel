<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

<<<<<<< HEAD
=======
use Illuminate\Database\Eloquent\Model;

>>>>>>> origin/main
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
 * @property string|null $failure_category
 * @property array<string, mixed>|null $payload
<<<<<<< HEAD
 * @property int|null $progress_current
 * @property int|null $progress_total
 * @property string|null $progress_description
 * @property \DateTimeImmutable|null $progress_updated_at
 * @property int|null $outcome_processed
 * @property int|null $outcome_skipped
 * @property int|null $outcome_warnings_count
 * @property string|null $outcome_status
 */
final class JobRecordModel extends JobsMonitorModel
{
    protected $table = 'jobs_monitor';

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'job_class',
        'connection',
        'queue',
        'status',
        'attempt',
        'started_at',
        'finished_at',
        'duration_ms',
        'exception',
        'failure_category',
        'payload',
        'progress_current',
        'progress_total',
        'progress_description',
        'progress_updated_at',
        'outcome_processed',
        'outcome_skipped',
        'outcome_warnings_count',
        'outcome_status',
        'failure_fingerprint',
    ];
=======
 */
final class JobRecordModel extends Model
{
    protected $table = 'jobs_monitor';

    protected $guarded = [];
>>>>>>> origin/main

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
        'attempt' => 'integer',
        'duration_ms' => 'integer',
        'payload' => 'array',
<<<<<<< HEAD
        'progress_current' => 'integer',
        'progress_total' => 'integer',
        'progress_updated_at' => 'immutable_datetime',
        'outcome_processed' => 'integer',
        'outcome_skipped' => 'integer',
        'outcome_warnings_count' => 'integer',
=======
>>>>>>> origin/main
    ];
}
