<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateInterval;
use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;

/**
 * Transitions scheduled-task runs that stayed in the Running state past
 * the configured deadline into the Late state. A "late" record means the
 * worker process crashed, the host died, or the command hung without ever
 * firing ScheduledTaskFinished/Failed.
 */
final class DetectLateScheduledTasksAction
{
    public function __construct(
        private readonly ScheduledTaskRunRepository $repository,
    ) {}

    public function __invoke(DateTimeImmutable $now, int $toleranceMinutes = 30): int
    {
        $deadline = $now->sub(new DateInterval('PT'.max(1, $toleranceMinutes).'M'));

        $count = 0;
        foreach ($this->repository->findStuckRunning($deadline) as $run) {
            $run->markAsLate($now);
            $this->repository->save($run);
            $count++;
        }

        return $count;
    }
}
