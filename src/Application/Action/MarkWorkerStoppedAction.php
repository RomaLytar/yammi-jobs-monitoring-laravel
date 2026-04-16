<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

/**
 * Mark the given worker as intentionally stopped. Called by the queue
 * event subscriber on `WorkerStopping`. No-op when the worker has not
 * been seen before — the package never invents rows for unknown ids.
 */
final class MarkWorkerStoppedAction
{
    public function __construct(
        private readonly WorkerRepository $repository,
    ) {}

    public function __invoke(WorkerIdentifier $id, DateTimeImmutable $stoppedAt): void
    {
        $this->repository->markStopped($id, $stoppedAt);
    }
}
