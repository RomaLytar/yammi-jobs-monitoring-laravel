<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use Yammi\JobsMonitor\Application\Action\RecordScheduledTaskRunAction;
use Yammi\JobsMonitor\Application\DTO\ScheduledTaskRunData;
use Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\ScheduledTaskRunModel;

/**
 * JSON twin of ScheduledTasksController. Mirrors every UI-side operation
 * (paginated list with filters/sort, status counters, retry) so SPA /
 * mobile / external integrations get the same surface as the Blade page.
 *
 * @internal
 */
final class ScheduledTasksApiController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly ScheduledTaskRunRepository $repository,
        private readonly RecordScheduledTaskRunAction $recorder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', (string) self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) $request->query('page', '1'));

        $result = $this->repository->findPaginated($perPage, $page, [
            'status' => trim((string) $request->query('status', '')),
            'search' => trim((string) $request->query('search', '')),
            'sort' => (string) $request->query('sort', 'started_at'),
            'dir' => (string) $request->query('dir', 'desc'),
        ]);

        return new JsonResponse([
            'data' => array_map(fn (ScheduledTaskRun $r) => $this->summarize($r), $result['rows']),
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil(($result['total'] ?: 1) / $perPage)),
                'sort' => $request->query('sort', 'started_at'),
                'dir' => $request->query('dir', 'desc'),
            ],
        ]);
    }

    public function statusCounts(): JsonResponse
    {
        return new JsonResponse(['counts' => $this->repository->statusCounts()]);
    }

    public function retry(int $id): JsonResponse
    {
        $row = ScheduledTaskRunModel::query()->find($id);
        if ($row === null) {
            return new JsonResponse(['error' => 'Scheduled run not found.'], 404);
        }

        $command = $this->extractArtisanCommand($row->command, $row->task_name);
        if ($command === null) {
            return new JsonResponse([
                'error' => 'This run is not an artisan command, cannot re-run from here.',
            ], 422);
        }

        $startedAt = new DateTimeImmutable;

        try {
            $exitCode = Artisan::call($command);
            $output = trim(Artisan::output());
            $finishedAt = new DateTimeImmutable;
            $succeeded = $exitCode === 0;

            $this->recordRetryRun($row, $startedAt, $finishedAt, $succeeded, $exitCode, $output, null);

            return new JsonResponse([
                'command' => $command,
                'exit_code' => $exitCode,
                'succeeded' => $succeeded,
                'output' => $output,
                'started_at' => $startedAt->format(DATE_ATOM),
                'finished_at' => $finishedAt->format(DATE_ATOM),
            ]);
        } catch (Throwable $e) {
            $this->recordRetryRun(
                row: $row,
                startedAt: $startedAt,
                finishedAt: new DateTimeImmutable,
                succeeded: false,
                exitCode: null,
                output: null,
                exception: sprintf('%s: %s', $e::class, $e->getMessage()),
            );

            return new JsonResponse([
                'error' => sprintf('Re-run failed: %s', $e->getMessage()),
            ], 500);
        }
    }

    /**
     * @return array{id: int, mutex: string, task_name: string, command: ?string, expression: string, timezone: ?string, status: string, started_at: string, finished_at: ?string, duration_ms: ?int, exit_code: ?int, exception: ?string, host: ?string}
     */
    private function summarize(ScheduledTaskRun $run): array
    {
        $model = ScheduledTaskRunModel::query()
            ->where('mutex', $run->mutex)
            ->where('started_at', $run->startedAt)
            ->first();

        return [
            'id' => $model !== null ? (int) $model->id : 0,
            'mutex' => $run->mutex,
            'task_name' => $run->taskName,
            'command' => $run->command,
            'expression' => $run->expression,
            'timezone' => $run->timezone,
            'status' => $run->status()->value,
            'started_at' => $run->startedAt->format(DATE_ATOM),
            'finished_at' => $run->finishedAt()?->format(DATE_ATOM),
            'duration_ms' => $run->duration()?->milliseconds,
            'exit_code' => $run->exitCode(),
            'exception' => $run->exception(),
            'host' => $run->host,
        ];
    }

    private function recordRetryRun(
        ScheduledTaskRunModel $row,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $finishedAt,
        bool $succeeded,
        ?int $exitCode,
        ?string $output,
        ?string $exception,
    ): void {
        ($this->recorder)(new ScheduledTaskRunData(
            mutex: $row->mutex,
            taskName: $row->task_name.' · manual retry',
            expression: $row->expression,
            timezone: $row->timezone,
            status: $succeeded ? ScheduledTaskStatus::Success : ScheduledTaskStatus::Failed,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            exitCode: $exitCode,
            output: $output,
            exception: $exception,
            host: gethostname() ?: 'manual',
            command: $row->command,
        ));
    }

    private function extractArtisanCommand(?string $command, ?string $taskName): ?string
    {
        foreach ([$command, $taskName] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            if (preg_match('/\bartisan\s+(.+)$/u', $candidate, $m) === 1) {
                return trim($m[1]);
            }
        }

        return null;
    }
}
