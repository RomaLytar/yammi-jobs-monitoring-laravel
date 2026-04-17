<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Application\DTO\JobClassStatsData;
use Yammi\JobsMonitor\Application\DTO\PagedResult;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Domain\Shared\ValueObject\Period;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;

/**
 * Programmatic read surface behind the YammiJobs facade. Wraps domain
 * repositories and exposes paginated results keyed by Period VO so host
 * applications can reach the same data the dashboard displays without
 * making HTTP calls.
 */
final class YammiJobsQueryService
{
    public const DEFAULT_PER_PAGE = 50;

    public function __construct(
        private readonly JobRecordRepository $jobs,
        private readonly FailureGroupRepository $failureGroups,
        private readonly ScheduledTaskRunRepository $scheduledRuns,
        private readonly WorkerRepository $workers,
        private readonly QueueMetricsDriver $metricsDriver,
    ) {}

    /**
     * @param  string|Period|null  $period
     * @return PagedResult<JobRecord>
     */
    public function jobs(
        mixed $period = null,
        ?string $jobClass = null,
        ?JobStatus $status = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): PagedResult {
        $since = Period::fromValue($period)->from();

        $items = $this->jobs->findPaginated(
            since: $since,
            search: $jobClass,
            perPage: $perPage,
            page: $page,
            statusFilter: $status,
        );
        $total = $this->jobs->countFiltered(
            since: $since,
            search: $jobClass,
            statusFilter: $status,
        );

        return new PagedResult($items, $total, $page, $perPage);
    }

    /**
     * @param  string|Period|null  $period
     * @return PagedResult<JobRecord>
     */
    public function failed(
        mixed $period = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): PagedResult {
        return $this->jobs($period, null, JobStatus::Failed, $page, $perPage);
    }

    /**
     * @return array<JobRecord>
     */
    public function attempts(string $uuid): array
    {
        return $this->jobs->findAllAttempts(new JobIdentifier($uuid));
    }

    public function job(string $uuid, int $attempt): ?JobRecord
    {
        return $this->jobs->findByIdentifierAndAttempt(
            new JobIdentifier($uuid),
            new Attempt($attempt),
        );
    }

    /**
     * @return PagedResult<JobRecord>
     */
    public function dlq(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE, int $maxTries = 3): PagedResult
    {
        $items = $this->jobs->findDeadLetterJobs($perPage, $page, $maxTries);
        $total = $this->jobs->countDeadLetterJobs($maxTries);

        return new PagedResult($items, $total, $page, $perPage);
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function dlqPayload(string $uuid): ?array
    {
        $attempts = $this->jobs->findAllAttempts(new JobIdentifier($uuid));

        if ($attempts === []) {
            return null;
        }

        return $attempts[count($attempts) - 1]->payload();
    }

    /**
     * @return PagedResult<FailureGroup>
     */
    public function failureGroups(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): PagedResult
    {
        $items = $this->failureGroups->listOrderedByLastSeen($perPage, ($page - 1) * $perPage);
        $total = $this->failureGroups->countAll();

        return new PagedResult($items, $total, $page, $perPage);
    }

    public function failureGroup(string $fingerprint): ?FailureGroup
    {
        return $this->failureGroups->findByFingerprint(new FailureFingerprint($fingerprint));
    }

    public function stats(string $jobClass): JobClassStatsData
    {
        $raw = $this->jobs->aggregateStatsByClass($jobClass);

        return new JobClassStatsData(
            jobClass: $jobClass,
            total: $raw['total'],
            processed: $raw['processed'],
            failed: $raw['failed'],
            avgDurationMs: $raw['avg_duration_ms'],
        );
    }

    /**
     * @param  string|Period|null  $period
     * @return array<array{job_class: string, total: int, processed: int, failed: int, avg_duration_ms: float|null, max_duration_ms: int|null, retry_count: int}>
     */
    public function statsAll(mixed $period = null): array
    {
        return $this->jobs->aggregateStatsByClassMulti(Period::fromValue($period)->from());
    }

    /**
     * @param  string|Period|null  $period
     * @return array{total: int, processed: int, failed: int, processing: int}
     */
    public function statusCounts(mixed $period = null): array
    {
        return $this->jobs->statusCounts(Period::fromValue($period)->from(), null);
    }

    /**
     * @param  array{status?: string, search?: string, sort?: string, dir?: string}  $filters
     * @return PagedResult<ScheduledTaskRun>
     */
    public function scheduled(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): PagedResult
    {
        $result = $this->scheduledRuns->findPaginated($perPage, $page, $filters);

        return new PagedResult($result['rows'], $result['total'], $page, $perPage);
    }

    /**
     * @return array<string, int>
     */
    public function scheduledStatusCounts(): array
    {
        return $this->scheduledRuns->statusCounts();
    }

    /**
     * @return PagedResult<Worker>
     */
    public function workers(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): PagedResult
    {
        $items = $this->workers->findPaginated($perPage, $page);
        $total = $this->workers->countAll();

        return new PagedResult($items, $total, $page, $perPage);
    }

    /**
     * @return array<string, int>
     */
    public function aliveWorkersByQueue(\DateTimeImmutable $aliveSince): array
    {
        return $this->workers->countAliveByQueueKey($aliveSince);
    }

    /**
     * @param  string|Period|null  $period
     */
    public function countFailures(mixed $period = null, ?int $minAttempt = null): int
    {
        $since = Period::fromValue($period)->from() ?? new \DateTimeImmutable('@0');

        return $this->jobs->countFailuresSince($since, $minAttempt);
    }

    /**
     * @param  string|Period|null  $period
     */
    public function countPartialCompletions(mixed $period = null): int
    {
        $since = Period::fromValue($period)->from() ?? new \DateTimeImmutable('@0');

        return $this->jobs->countPartialCompletionsSince($since);
    }

    /**
     * @param  string|Period|null  $period
     */
    public function countSilentSuccesses(mixed $period = null): int
    {
        $since = Period::fromValue($period)->from() ?? new \DateTimeImmutable('@0');

        return $this->jobs->countZeroProcessedSince($since);
    }

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
}
