<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/** @internal */
final class DashboardViewModel
{
    private const JOBS_PER_PAGE = 50;

    private const FAILURES_PER_PAGE = 10;

    private const PERIODS = [
        '30m' => '30 minutes',
        '1h' => '1 hour',
        '6h' => '6 hours',
        '24h' => '24 hours',
        '7d' => '7 days',
        '30d' => '30 days',
        'all' => null,
    ];

    private const SORTABLE = ['started_at', 'status', 'duration_ms', 'job_class'];

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @param  array<int, array<string, mixed>>  $failures
     * @param  array{total: int, processed: int, failed: int, processing: int}  $statusCounts
     * @param  array<string, string|null>  $periods
     * @param  list<string>  $availableQueues
     * @param  list<string>  $availableConnections
     */
    public function __construct(
        public readonly array $jobs,
        public readonly int $jobsTotal,
        public readonly int $jobsPage,
        public readonly int $jobsLastPage,
        public readonly string $jobsSort,
        public readonly string $jobsDir,
        public readonly array $failures,
        public readonly int $failuresTotal,
        public readonly int $failuresPage,
        public readonly int $failuresLastPage,
        public readonly string $failuresSort,
        public readonly string $failuresDir,
        public readonly array $statusCounts,
        public readonly string $period,
        public readonly string $search,
        public readonly array $periods,
        public readonly string $status,
        public readonly string $queue,
        public readonly string $connection,
        public readonly string $failureCategory,
        public readonly array $availableQueues,
        public readonly array $availableConnections,
    ) {}

    /**
     * @param  array{page: int, sort: string, dir: string, fpage: int, fsort: string, fdir: string, status: string, queue: string, connection: string, failure_category: string}  $params
     */
    public static function fromRepository(
        JobRecordRepository $repository,
        string $period,
        string $search,
        array $params,
        ?PayloadRedactor $redactor = null,
    ): self {
        $since = self::periodToSince($period);
        $searchTerm = $search !== '' ? $search : null;

        $jobsSort = in_array($params['sort'], self::SORTABLE, true) ? $params['sort'] : 'started_at';
        $jobsDir = strtolower($params['dir']) === 'asc' ? 'asc' : 'desc';
        $failuresSort = in_array($params['fsort'], ['started_at', 'duration_ms', 'job_class'], true) ? $params['fsort'] : 'started_at';
        $failuresDir = strtolower($params['fdir']) === 'asc' ? 'asc' : 'desc';

        $statusFilter = JobStatus::tryFrom($params['status']);
        $queueFilter = $params['queue'] !== '' ? $params['queue'] : null;
        $connectionFilter = $params['connection'] !== '' ? $params['connection'] : null;
        $failureCategoryFilter = FailureCategory::tryFrom($params['failure_category']);

        // All jobs
        $jobsTotal = $repository->countFiltered(
            $since,
            $searchTerm,
            $statusFilter,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );
        $jobsLastPage = max(1, (int) ceil($jobsTotal / self::JOBS_PER_PAGE));
        $jobsPage = min(max(1, $params['page']), $jobsLastPage);
        $jobRecords = $repository->findPaginated(
            $since,
            $searchTerm,
            self::JOBS_PER_PAGE,
            $jobsPage,
            $jobsSort,
            $jobsDir,
            $statusFilter,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        // Failed jobs (always force status=Failed, but still honor queue/connection/category filters)
        $failuresTotal = $repository->countFiltered(
            $since,
            $searchTerm,
            JobStatus::Failed,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );
        $failuresLastPage = max(1, (int) ceil($failuresTotal / self::FAILURES_PER_PAGE));
        $failuresPage = min(max(1, $params['fpage']), $failuresLastPage);
        $failedRecords = $repository->findPaginated(
            $since,
            $searchTerm,
            self::FAILURES_PER_PAGE,
            $failuresPage,
            $failuresSort,
            $failuresDir,
            JobStatus::Failed,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        $counts = $repository->statusCounts(
            $since,
            $searchTerm,
            $queueFilter,
            $connectionFilter,
            $failureCategoryFilter,
        );

        return new self(
            jobs: array_map(static fn (JobRecord $r) => self::formatRecord($r, $redactor), $jobRecords),
            jobsTotal: $jobsTotal,
            jobsPage: $jobsPage,
            jobsLastPage: $jobsLastPage,
            jobsSort: $jobsSort,
            jobsDir: $jobsDir,
            failures: array_map(static fn (JobRecord $r) => self::formatRecord($r, $redactor), $failedRecords),
            failuresTotal: $failuresTotal,
            failuresPage: $failuresPage,
            failuresLastPage: $failuresLastPage,
            failuresSort: $failuresSort,
            failuresDir: $failuresDir,
            statusCounts: $counts,
            period: $period,
            search: $search,
            periods: self::PERIODS,
            status: $statusFilter?->value ?? '',
            queue: $queueFilter ?? '',
            connection: $connectionFilter ?? '',
            failureCategory: $failureCategoryFilter?->value ?? '',
            availableQueues: $repository->distinctQueues(),
            availableConnections: $repository->distinctConnections(),
        );
    }

    public function successRate(): string
    {
        $total = $this->statusCounts['total'];

        if ($total === 0) {
            return '—';
        }

        $rate = ($this->statusCounts['processed'] / $total) * 100;

        return number_format($rate, 1).'%';
    }

    private static function periodToSince(string $period): ?\DateTimeImmutable
    {
        if ($period === 'all' || ! isset(self::PERIODS[$period])) {
            return null;
        }

        return new \DateTimeImmutable('-'.self::PERIODS[$period]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatRecord(JobRecord $record, ?PayloadRedactor $redactor = null): array
    {
        $payload = $record->payload();

        if ($payload !== null && $redactor !== null) {
            $payload = $redactor->redact($payload);
        }

        return [
            'uuid' => $record->id->value,
            'attempt' => $record->attempt->value,
            'job_class' => $record->jobClass,
            'short_class' => self::shortClassName($record->jobClass),
            'connection' => $record->connection,
            'queue' => $record->queue->value,
            'status' => $record->status()->value,
            'started_at' => $record->startedAt->format('Y-m-d H:i:s'),
            'finished_at' => $record->finishedAt()?->format('Y-m-d H:i:s'),
            'duration_ms' => $record->duration()?->milliseconds,
            'duration_formatted' => self::formatDuration($record->duration()?->milliseconds),
            'exception' => $record->exception(),
            'failure_category' => $record->failureCategory()?->value,
            'failure_category_label' => $record->failureCategory()?->label(),
            'is_failed' => $record->status()->isFailure(),
            'payload' => $payload,
        ];
    }

    private static function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    private static function formatDuration(?int $ms): string
    {
        if ($ms === null) {
            return '—';
        }

        if ($ms < 1000) {
            return number_format($ms).'ms';
        }

        return number_format($ms / 1000, 2).'s';
    }
}
