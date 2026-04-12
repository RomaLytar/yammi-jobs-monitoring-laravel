<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Application\DTO\JobClassStatsData;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

final class JobsMonitorService
{
    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly QueueMetricsDriver $metricsDriver,
    ) {}

    public function queueSize(string $queue): ?int
    {
        return $this->metricsDriver->getQueueSize($queue);
    }

    public function delayedSize(string $queue): ?int
    {
        return $this->metricsDriver->getDelayedSize($queue);
    }

    public function reservedSize(string $queue): ?int
    {
        return $this->metricsDriver->getReservedSize($queue);
    }

    /**
     * @return array<JobRecord>
     */
    public function recentJobs(int $limit = 50): array
    {
        return $this->repository->findRecent($limit);
    }

    /**
     * @return array<JobRecord>
     */
    public function recentFailures(int $hours = 24): array
    {
        return $this->repository->findRecentFailures($hours);
    }

    public function stats(string $jobClass): JobClassStatsData
    {
        $raw = $this->repository->aggregateStatsByClass($jobClass);

        return new JobClassStatsData(
            jobClass: $jobClass,
            total: $raw['total'],
            processed: $raw['processed'],
            failed: $raw['failed'],
            avgDurationMs: $raw['avg_duration_ms'],
        );
    }
}
