<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Worker\Entity\Worker;
use Yammi\JobsMonitor\Domain\Worker\Enum\WorkerStatus;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;

/**
 * JSON surface for the /workers page.
 *  - GET index — paginated list with status
 *  - GET status-counts — alive/silent/dead counters + observed-vs-expected coverage
 *
 * @internal
 */
final class WorkersApiController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 200;

    public function index(Request $request, WorkerRepository $repository, ConfigRepository $config): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = $this->page($request);
        $statusFilter = $this->statusFilter($request);
        $silentAfterSeconds = (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120);
        $now = new DateTimeImmutable;

        $all = $repository->findAll();
        $filtered = [];
        foreach ($all as $worker) {
            $status = $worker->classifyStatus($now, $silentAfterSeconds);
            if ($statusFilter !== null && $status !== $statusFilter) {
                continue;
            }

            $filtered[] = [$worker, $status];
        }

        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        $data = array_map(
            fn (array $row): array => $this->summarize($row[0], $row[1]),
            $slice,
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => $this->meta($total, $page, $perPage) + [
                'silent_after_seconds' => $silentAfterSeconds,
            ],
        ]);
    }

    public function statusCounts(
        WorkerRepository $repository,
        ConfigRepository $config,
    ): JsonResponse {
        $silentAfterSeconds = (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120);
        $now = new DateTimeImmutable;

        $alive = 0;
        $silent = 0;
        $dead = 0;
        $observed = [];
        foreach ($repository->findAll() as $worker) {
            $status = $worker->classifyStatus($now, $silentAfterSeconds);
            match ($status) {
                WorkerStatus::Alive => $alive++,
                WorkerStatus::Silent => $silent++,
                WorkerStatus::Dead => $dead++,
            };

            if ($status === WorkerStatus::Alive) {
                $key = $worker->heartbeat()->queueKey();
                $observed[$key] = ($observed[$key] ?? 0) + 1;
            }
        }

        /** @var array<mixed, mixed> $rawExpected */
        $rawExpected = (array) $config->get('jobs-monitor.workers.expected', []);
        $coverage = [];
        foreach ($rawExpected as $queueKey => $min) {
            if (! is_string($queueKey) || $queueKey === '') {
                continue;
            }
            $actual = $observed[$queueKey] ?? 0;
            $expected = max(0, (int) $min);
            $coverage[] = [
                'queue_key' => $queueKey,
                'observed' => $actual,
                'expected' => $expected,
                'status' => $actual >= $expected ? 'ok' : ($actual === 0 ? 'down' : 'degraded'),
            ];
        }

        return new JsonResponse([
            'data' => [
                'alive' => $alive,
                'silent' => $silent,
                'dead' => $dead,
                'coverage' => $coverage,
            ],
            'meta' => [
                'silent_after_seconds' => $silentAfterSeconds,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Worker $worker, WorkerStatus $status): array
    {
        $hb = $worker->heartbeat();

        return [
            'worker_id' => $hb->workerId->value,
            'connection' => $hb->connection,
            'queue' => $hb->queue,
            'queue_key' => $hb->queueKey(),
            'host' => $hb->host,
            'pid' => $hb->pid,
            'last_seen_at' => $hb->lastSeenAt->format(DATE_ATOM),
            'stopped_at' => $worker->stoppedAt()?->format(DATE_ATOM),
            'status' => $status->value,
        ];
    }

    private function statusFilter(Request $request): ?WorkerStatus
    {
        $value = trim((string) $request->query('status', ''));
        if ($value === '') {
            return null;
        }

        return WorkerStatus::tryFrom($value);
    }

    private function perPage(Request $request): int
    {
        return min(
            self::MAX_PER_PAGE,
            max(1, (int) $request->query('per_page', (string) self::DEFAULT_PER_PAGE)),
        );
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', '1'));
    }

    /**
     * @return array{total: int, page: int, per_page: int, last_page: int}
     */
    private function meta(int $total, int $page, int $perPage): array
    {
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil(($total ?: 1) / $perPage)),
        ];
    }
}
