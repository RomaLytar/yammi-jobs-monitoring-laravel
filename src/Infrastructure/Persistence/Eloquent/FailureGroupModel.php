<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal Infrastructure detail. Never returned past the repository
 *           boundary; the repository maps it to a {@see \Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup}.
 *
 * @property string $fingerprint
 * @property \DateTimeImmutable $first_seen_at
 * @property \DateTimeImmutable $last_seen_at
 * @property int $occurrences
 * @property array<int, string> $affected_job_classes
 * @property string $last_job_uuid
 * @property string $sample_exception_class
 * @property string $sample_message
 * @property string $sample_stack_trace
 */
final class FailureGroupModel extends Model
{
    protected $table = 'jobs_monitor_failure_groups';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'first_seen_at' => 'immutable_datetime',
        'last_seen_at' => 'immutable_datetime',
        'occurrences' => 'integer',
        'affected_job_classes' => 'array',
    ];
}
