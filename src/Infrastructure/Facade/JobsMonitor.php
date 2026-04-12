<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Facade;

use Illuminate\Support\Facades\Facade;
use Yammi\JobsMonitor\Application\DTO\JobClassStatsData;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;

/**
 * @method static ?int queueSize(string $queue)
 * @method static ?int delayedSize(string $queue)
 * @method static ?int reservedSize(string $queue)
 * @method static array<JobRecord> recentJobs(int $limit = 50)
 * @method static array<JobRecord> recentFailures(int $hours = 24)
 * @method static JobClassStatsData stats(string $jobClass)
 *
 * @see JobsMonitorService
 */
final class JobsMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return JobsMonitorService::class;
    }
}
