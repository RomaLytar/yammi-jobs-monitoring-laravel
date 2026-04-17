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
 * Public read facade for JobsMonitor. Accepts a period as:
 *   - compact string: '30m', '1h', '24h', '7d', '30d'
 *   - literal 'all' for "all time" — the default
 *   - Period VO: Period::last('1h') / Period::between($from, $to) / Period::since($from)
 *
 * Pagination arguments (`page`, `perPage`) are always optional — they default to page = 1, perPage = 50.
 *
 * @method static PagedResult<JobRecord> jobs(string|Period $period = 'all', ?string $jobClass = null, ?JobStatus $status = null, int $page = 1, int $perPage = 50)
 * @method static PagedResult<JobRecord> failed(string|Period $period = 'all', int $page = 1, int $perPage = 50)
 * @method static array<JobRecord> attempts(string $uuid)
 * @method static ?JobRecord job(string $uuid, int $attempt)
 * @method static PagedResult<JobRecord> dlq(int $page = 1, int $perPage = 50, int $maxTries = 3)
 * @method static array<int|string,mixed>|null dlqPayload(string $uuid)
 * @method static PagedResult<FailureGroup> failureGroups(int $page = 1, int $perPage = 50)
 * @method static ?FailureGroup failureGroup(string $fingerprint)
 * @method static PagedResult<ScheduledTaskRun> scheduled(array $filters = [], int $page = 1, int $perPage = 50)
 * @method static array<string,int> scheduledStatusCounts()
 * @method static PagedResult<Worker> workers(int $page = 1, int $perPage = 50)
 * @method static array<string,int> aliveWorkersByQueue(DateTimeImmutable $aliveSince)
 * @method static int countFailures(string|Period $period = 'all', ?int $minAttempt = null)
 * @method static int countPartialCompletions(string|Period $period = 'all')
 * @method static int countSilentSuccesses(string|Period $period = 'all')
 * @method static JobClassStatsData stats(string $jobClass)
 * @method static array statsAll(string|Period $period = 'all')
 * @method static array statusCounts(string|Period $period = 'all')
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
