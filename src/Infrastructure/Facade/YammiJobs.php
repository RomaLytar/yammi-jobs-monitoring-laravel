<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Facade;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Yammi\JobsMonitor\Application\DTO\JobClassStatsData;
use Yammi\JobsMonitor\Application\DTO\PagedResult;
use Yammi\JobsMonitor\Application\Service\YammiJobsQueryService;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Shared\ValueObject\Period;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;

/**
 * Public read facade for JobsMonitor. Accepts a period as either a
 * compact string ('30m', '1h', '24h', '7d', '30d'), a Period VO
 * (Period::last/between/since), or null for "all time".
 *
 * @method static PagedResult<JobRecord> jobs(string|Period|null $period = null, ?string $jobClass = null, ?JobStatus $status = null, int $page = 1, int $perPage = 50)
 * @method static PagedResult<JobRecord> failed(string|Period|null $period = null, int $page = 1, int $perPage = 50)
 * @method static array<JobRecord> attempts(string $uuid)
 * @method static ?JobRecord job(string $uuid, int $attempt)
 * @method static PagedResult<JobRecord> dlq(int $page = 1, int $perPage = 50, int $maxTries = 3)
 * @method static array<string,mixed>|null dlqPayload(string $uuid)
 * @method static PagedResult<FailureGroup> failureGroups(int $page = 1, int $perPage = 50)
 * @method static ?FailureGroup failureGroup(string $fingerprint)
 * @method static PagedResult<ScheduledTaskRun> scheduled(array $filters = [], int $page = 1, int $perPage = 50)
 * @method static array<string,int> scheduledStatusCounts()
 * @method static PagedResult<Worker> workers(int $page = 1, int $perPage = 50)
 * @method static array<string,int> aliveWorkersByQueue(DateTimeImmutable $aliveSince)
 * @method static int countFailures(string|Period|null $period = null, ?int $minAttempt = null)
 * @method static int countPartialCompletions(string|Period|null $period = null)
 * @method static int countSilentSuccesses(string|Period|null $period = null)
 * @method static JobClassStatsData stats(string $jobClass)
 * @method static array statsAll(string|Period|null $period = null)
 * @method static array statusCounts(string|Period|null $period = null)
 * @method static ?int queueSize(string $queue)
 * @method static ?int delayedSize(string $queue)
 * @method static ?int reservedSize(string $queue)
 *
 * @see YammiJobsQueryService
 */
final class YammiJobs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return YammiJobsQueryService::class;
    }
}
