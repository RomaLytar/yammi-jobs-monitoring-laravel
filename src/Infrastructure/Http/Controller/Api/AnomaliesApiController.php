<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;
use Yammi\JobsMonitor\Application\Action\RefreshDurationBaselinesAction;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationAnomalyModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationBaselineModel;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;

/**
 * JSON surface for the Anomalies dashboard:
 *  - duration baselines per job class
 *  - duration anomalies (short / long)
 *  - silent successes (suspicious outcome reports)
 *  - partial completions (failed mid-progress)
 *  - on-demand baseline refresh
 *
 * @internal
 */
final class AnomaliesApiController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 200;

    public function baselines(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = $this->page($request);

        $total = DurationBaselineModel::query()->count();
        $rows = DurationBaselineModel::query()
            ->orderBy('job_class')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new JsonResponse([
            'data' => $rows->map(fn (DurationBaselineModel $b) => [
                'job_class' => $b->job_class,
                'samples_count' => $b->samples_count,
                'p50_ms' => $b->p50_ms,
                'p95_ms' => $b->p95_ms,
                'min_ms' => $b->min_ms,
                'max_ms' => $b->max_ms,
                'computed_over_from' => $b->computed_over_from->format(DATE_ATOM),
                'computed_over_to' => $b->computed_over_to->format(DATE_ATOM),
            ])->all(),
            'meta' => $this->meta($total, $page, $perPage),
        ]);
    }

    public function anomalies(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = $this->page($request);

        $kind = trim((string) $request->query('kind', ''));

        $query = DurationAnomalyModel::query();
        if (in_array($kind, ['short', 'long'], true)) {
            $query->where('kind', $kind);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new JsonResponse([
            'data' => $rows->map(fn (DurationAnomalyModel $a) => [
                'job_uuid' => $a->job_uuid,
                'attempt' => $a->attempt,
                'job_class' => $a->job_class,
                'kind' => $a->kind,
                'duration_ms' => $a->duration_ms,
                'baseline_p50_ms' => $a->baseline_p50_ms,
                'baseline_p95_ms' => $a->baseline_p95_ms,
                'samples_count' => $a->samples_count,
                'detected_at' => $a->detected_at->format(DATE_ATOM),
            ])->all(),
            'meta' => $this->meta($total, $page, $perPage) + [
                'short_count' => DurationAnomalyModel::query()->where('kind', 'short')->count(),
                'long_count' => DurationAnomalyModel::query()->where('kind', 'long')->count(),
            ],
        ]);
    }

    public function silentSuccesses(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = $this->page($request);

        $query = JobRecordModel::query()
            ->where('status', JobStatus::Processed->value)
            ->where(function ($q): void {
                $q->whereIn('outcome_status', ['no_op', 'degraded'])
                    ->orWhere('outcome_processed', 0)
                    ->orWhere('outcome_warnings_count', '>', 0);
            });

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('finished_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new JsonResponse([
            'data' => $rows->map(fn (JobRecordModel $j) => [
                'uuid' => $j->uuid,
                'attempt' => (int) $j->attempt,
                'job_class' => $j->job_class,
                'queue' => $j->queue,
                'finished_at' => $j->finished_at?->format(DATE_ATOM),
                'duration_ms' => $j->duration_ms,
                'outcome_status' => $j->outcome_status,
                'outcome_processed' => $j->outcome_processed,
                'outcome_skipped' => $j->outcome_skipped,
                'outcome_warnings_count' => $j->outcome_warnings_count,
                'has_payload' => ! empty($j->payload),
            ])->all(),
            'meta' => $this->meta($total, $page, $perPage),
        ]);
    }

    public function partialCompletions(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = $this->page($request);

        $query = JobRecordModel::query()
            ->where('status', JobStatus::Failed->value)
            ->whereNotNull('progress_current')
            ->where('progress_current', '>', 0);

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('finished_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new JsonResponse([
            'data' => $rows->map(fn (JobRecordModel $j) => [
                'uuid' => $j->uuid,
                'attempt' => (int) $j->attempt,
                'job_class' => $j->job_class,
                'queue' => $j->queue,
                'finished_at' => $j->finished_at?->format(DATE_ATOM),
                'duration_ms' => $j->duration_ms,
                'progress_current' => $j->progress_current,
                'progress_total' => $j->progress_total,
                'progress_description' => $j->progress_description,
                'progress_percentage' => $j->progress_total > 0
                    ? round((int) $j->progress_current / (int) $j->progress_total * 100, 2)
                    : null,
                'exception' => $j->exception,
                'has_payload' => ! empty($j->payload),
            ])->all(),
            'meta' => $this->meta($total, $page, $perPage),
        ]);
    }

    public function refreshBaselines(Request $request, RefreshDurationBaselinesAction $action): JsonResponse
    {
        $lookback = max(1, min(90, (int) $request->input('lookback_days', 7)));

        try {
            $updated = $action(new DateTimeImmutable, $lookback);

            return new JsonResponse([
                'updated' => $updated,
                'lookback_days' => $lookback,
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'error' => sprintf('Refresh failed: %s', $e::class),
            ], 500);
        }
    }

    private function perPage(Request $request): int
    {
        return min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', (string) self::DEFAULT_PER_PAGE)));
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
