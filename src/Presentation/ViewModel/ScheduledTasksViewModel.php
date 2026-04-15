<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\ScheduledTaskRunModel;

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
        /** @var array<string, int> Map of (mutex|YmdHis) → row id, used by retry buttons. */
        public readonly array $rowIds,
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

        // Look up DB primary keys for rendered rows so the Blade can build
        // retry-action URLs without exposing the model in the view layer.
        $rowIds = self::collectIds(array_merge($result['rows'], $failed['rows']));

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
            rowIds: $rowIds,
        );
    }

    public function rowKey(ScheduledTaskRun $run): string
    {
        return $run->mutex.'|'.$run->startedAt->format('Y-m-d H:i:s.u');
    }

    /**
     * @param  list<ScheduledTaskRun>  $runs
     * @return array<string, int>
     */
    private static function collectIds(array $runs): array
    {
        if ($runs === []) {
            return [];
        }

        $pairs = array_map(static fn (ScheduledTaskRun $r) => [
            'mutex' => $r->mutex,
            'started_at' => $r->startedAt->format('Y-m-d H:i:s.u'),
        ], $runs);

        $query = ScheduledTaskRunModel::query();
        foreach ($pairs as $i => $p) {
            if ($i === 0) {
                $query->where(function ($q) use ($p): void {
                    $q->where('mutex', $p['mutex'])->where('started_at', $p['started_at']);
                });
            } else {
                $query->orWhere(function ($q) use ($p): void {
                    $q->where('mutex', $p['mutex'])->where('started_at', $p['started_at']);
                });
            }
        }

        $rows = $query->get(['id', 'mutex', 'started_at']);
        $map = [];
        foreach ($rows as $row) {
            $map[$row->mutex.'|'.$row->started_at->format('Y-m-d H:i:s.u')] = (int) $row->id;
        }

        return $map;
    }
}
