<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;

final class ScheduledTasksViewModel
{
    private const PER_PAGE = 50;

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
    ) {}

    public static function fromRepository(
        ScheduledTaskRunRepository $repository,
        int $page,
        string $status,
        string $search,
        string $sort,
        string $dir,
    ): self {
        $page = max(1, $page);
        $sort = $sort !== '' ? $sort : 'started_at';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $result = $repository->findPaginated(self::PER_PAGE, $page, [
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        $lastPage = (int) max(1, ceil(($result['total'] ?: 1) / self::PER_PAGE));

        return new self(
            rows: $result['rows'],
            total: $result['total'],
            page: $page,
            lastPage: $lastPage,
            status: $status,
            search: $search,
            sort: $sort,
            dir: $dir,
            statusCounts: $repository->statusCounts(),
        );
    }
}
