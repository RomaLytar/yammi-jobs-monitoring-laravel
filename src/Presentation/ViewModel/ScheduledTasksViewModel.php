<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;

final class ScheduledTasksViewModel
{
    private const RUNS_PER_PAGE = 50;

    private const FAILED_PER_PAGE = 10;

    public function __construct(
        /** @var list<ScheduledTaskRun> */
        public readonly array $rows,
        public readonly int $total,
        public readonly int $page,
        public readonly int $lastPage,
        public readonly string $status,
        public readonly string $search,
        public readonly string $sort,
        public readonly string $dir,
        /** @var array<string, int> */
        public readonly array $statusCounts,
        /** @var list<ScheduledTaskRun> */
        public readonly array $failedRows,
        public readonly int $failedTotal,
        public readonly int $failedPage,
        public readonly int $failedLastPage,
    ) {}

    public static function fromRepository(
        ScheduledTaskRunRepository $repository,
        int $page,
        string $status,
        string $search,
        string $sort,
        string $dir,
        int $failedPage,
    ): self {
        $page = max(1, $page);
        $failedPage = max(1, $failedPage);
        $sort = $sort !== '' ? $sort : 'started_at';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $result = $repository->findPaginated(self::RUNS_PER_PAGE, $page, [
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        $failed = $repository->findPaginated(self::FAILED_PER_PAGE, $failedPage, [
            'status' => 'failed',
            'sort' => 'started_at',
            'dir' => 'desc',
        ]);

        return new self(
            rows: $result['rows'],
            total: $result['total'],
            page: $page,
            lastPage: (int) max(1, ceil(($result['total'] ?: 1) / self::RUNS_PER_PAGE)),
            status: $status,
            search: $search,
            sort: $sort,
            dir: $dir,
            statusCounts: $repository->statusCounts(),
            failedRows: $failed['rows'],
            failedTotal: $failed['total'],
            failedPage: $failedPage,
            failedLastPage: (int) max(1, ceil(($failed['total'] ?: 1) / self::FAILED_PER_PAGE)),
        );
    }
}
