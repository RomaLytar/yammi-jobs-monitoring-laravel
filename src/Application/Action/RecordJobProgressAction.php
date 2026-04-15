<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobProgress;

/**
 * Persists a progress tick for a running job. Idempotent: each call
 * overwrites the previous progress for the (uuid, attempt) pair.
 *
 * Called from the host app via the ReportsProgress trait — which
 * means this action runs on the queue worker process, inside handle().
 */
final class RecordJobProgressAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
    ) {}

    public function __invoke(
        string $uuid,
        int $attempt,
        int $current,
        ?int $total = null,
        ?string $description = null,
    ): void {
        $progress = new JobProgress(
            current: $current,
            total: $total,
            description: $description,
            updatedAt: new DateTimeImmutable,
        );

        $this->repository->recordProgress(
            new JobIdentifier($uuid),
            new Attempt($attempt),
            $progress,
        );
    }
}
