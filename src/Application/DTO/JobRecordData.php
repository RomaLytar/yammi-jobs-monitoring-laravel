<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;

/**
 * Cross-layer carrier for a single job lifecycle observation.
 *
 * The Infrastructure listener flattens a Laravel queue event into this
 * DTO and hands it to the StoreJobRecordAction. The Application layer
 * never sees Laravel types directly.
 *
 * Immutable, no behaviour. Optional fields are populated only for the
 * lifecycle stages where they make sense:
 *   - Processing → finishedAt and exception are null
 *   - Processed  → finishedAt is required, exception is null
 *   - Failed     → finishedAt is required, exception is required
 */
final class JobRecordData
{
    public function __construct(
        public readonly string $id,
        public readonly int $attempt,
        public readonly string $jobClass,
        public readonly string $connection,
        public readonly string $queue,
        public readonly JobStatus $status,
        public readonly DateTimeImmutable $startedAt,
        public readonly ?DateTimeImmutable $finishedAt = null,
        public readonly ?string $exception = null,
    ) {}
}
