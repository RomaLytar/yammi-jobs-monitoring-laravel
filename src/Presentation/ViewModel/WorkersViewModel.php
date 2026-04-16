<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\Enum\WorkerStatus;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;

final class WorkersViewModel
{
    private const PER_PAGE = 25;

    /**
     * @param  list<Worker>  $alive
     * @param  list<Worker>  $silent
     * @param  list<Worker>  $dead
     * @param  list<array{queue_key: string, observed: int, expected: int, status: string}>  $coverage
     */
    public function __construct(
        public readonly array $alive,
        public readonly int $aliveTotal,
        public readonly int $alivePage,
        public readonly int $aliveLastPage,
        public readonly array $silent,
        public readonly int $silentTotal,
        public readonly int $silentPage,
        public readonly int $silentLastPage,
        public readonly array $dead,
        public readonly int $deadTotal,
        public readonly int $deadPage,
        public readonly int $deadLastPage,
        public readonly array $coverage,
        public readonly int $coverageTotal,
        public readonly int $coveragePage,
        public readonly int $coverageLastPage,
        public readonly int $silentAfterSeconds,
        public readonly DateTimeImmutable $now,
    ) {}

    /**
     * @param  array<string, int>  $expected
     */
    public static function build(
        WorkerRepository $repository,
        int $silentAfterSeconds,
        array $expected,
        DateTimeImmutable $now,
        int $alivePage = 1,
        int $silentPage = 1,
        int $deadPage = 1,
        int $coveragePage = 1,
    ): self {
        $alivePage = max(1, $alivePage);
        $silentPage = max(1, $silentPage);
        $deadPage = max(1, $deadPage);
        $coveragePage = max(1, $coveragePage);

        $all = $repository->findAll();

        $aliveList = [];
        $silentList = [];
        $deadList = [];
        foreach ($all as $worker) {
            $status = $worker->classifyStatus($now, $silentAfterSeconds);
            match ($status) {
                WorkerStatus::Alive => $aliveList[] = $worker,
                WorkerStatus::Silent => $silentList[] = $worker,
                WorkerStatus::Dead => $deadList[] = $worker,
            };
        }

        $alivePaged = array_slice($aliveList, ($alivePage - 1) * self::PER_PAGE, self::PER_PAGE);
        $silentPaged = array_slice($silentList, ($silentPage - 1) * self::PER_PAGE, self::PER_PAGE);
        $deadPaged = array_slice($deadList, ($deadPage - 1) * self::PER_PAGE, self::PER_PAGE);

        $observed = [];
        foreach ($aliveList as $worker) {
            $key = $worker->heartbeat()->queueKey();
            $observed[$key] = ($observed[$key] ?? 0) + 1;
        }
        $coverage = self::buildCoverage($expected, $observed);
        $coveragePaged = array_slice($coverage, ($coveragePage - 1) * self::PER_PAGE, self::PER_PAGE);

        return new self(
            alive: array_values($alivePaged),
            aliveTotal: count($aliveList),
            alivePage: $alivePage,
            aliveLastPage: self::lastPage(count($aliveList)),
            silent: array_values($silentPaged),
            silentTotal: count($silentList),
            silentPage: $silentPage,
            silentLastPage: self::lastPage(count($silentList)),
            dead: array_values($deadPaged),
            deadTotal: count($deadList),
            deadPage: $deadPage,
            deadLastPage: self::lastPage(count($deadList)),
            coverage: array_values($coveragePaged),
            coverageTotal: count($coverage),
            coveragePage: $coveragePage,
            coverageLastPage: self::lastPage(count($coverage)),
            silentAfterSeconds: $silentAfterSeconds,
            now: $now,
        );
    }

    /**
     * @param  array<string, int>  $expected
     * @param  array<string, int>  $observed
     * @return list<array{queue_key: string, observed: int, expected: int, status: string}>
     */
    private static function buildCoverage(array $expected, array $observed): array
    {
        $rows = [];
        foreach ($expected as $queueKey => $min) {
            $actual = $observed[$queueKey] ?? 0;
            $rows[] = [
                'queue_key' => $queueKey,
                'observed' => $actual,
                'expected' => $min,
                'status' => $actual >= $min ? 'ok' : ($actual === 0 ? 'down' : 'degraded'),
            ];
        }

        return $rows;
    }

    private static function lastPage(int $total): int
    {
        return (int) max(1, ceil(($total ?: 1) / self::PER_PAGE));
    }
}
