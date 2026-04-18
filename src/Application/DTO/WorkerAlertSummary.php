<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

/**
 * Counts returned by DetectSilentWorkersAction so the calling command
 * (and its tests) can assert on how many events the tick produced.
 */
final class WorkerAlertSummary
{
    public function __construct(
        public readonly int $silentTriggered,
        public readonly int $silentResolved,
        public readonly int $underprovisionedTriggered,
        public readonly int $underprovisionedResolved,
    ) {}

    public function total(): int
    {
        return $this->silentTriggered
            + $this->silentResolved
            + $this->underprovisionedTriggered
            + $this->underprovisionedResolved;
    }
}
