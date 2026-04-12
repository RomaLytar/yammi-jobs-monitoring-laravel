<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

final class JobClassStatsData
{
    public readonly float $successRate;

    public function __construct(
        public readonly string $jobClass,
        public readonly int $total,
        public readonly int $processed,
        public readonly int $failed,
        public readonly ?float $avgDurationMs,
    ) {
        $this->successRate = $this->total > 0
            ? $this->processed / $this->total
            : 0.0;
    }
}
