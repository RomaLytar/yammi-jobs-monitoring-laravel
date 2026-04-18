<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Yammi\JobsMonitor\Application\Action\BulkDeleteDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\BulkRetryDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Infrastructure\Http\Request\DlqBulkOperationRequest;

/** @internal */
final class ApiController extends Controller
{
    private const PERIODS = [
        '1m' => '1 minute',
        '5m' => '5 minutes',
        '30m' => '30 minutes',
        '1h' => '1 hour',
        '6h' => '6 hours',
        '24h' => '24 hours',
        '7d' => '7 days',
        '30d' => '30 days',
        'all' => null,
    ];

    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly PayloadRedactor $redactor,
    ) {}

    public function jobs(Request $request): JsonResponse
    {
        $since = $this->parsePeriod($request);
        $search = $this->parseSearch($request);
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min((int) $request->query('per_page', '50'), 200);
        [$sortBy, $sortDir] = $this->parseSort($request);
        [$status, $queue, $connection, $category] = $this->parseFilters($request);

        $records = $this->repository->findPaginated(
            $since, $search, $perPage, $page, $sortBy, $sortDir,
            $status, $queue, $connection, $category,
        );
        $total = $this->repository->countFiltered(
            $since, $search, $status, $queue, $connection, $category,
        );

        return new JsonResponse([
            'data' => array_map([$this, 'serializeRecord'], $records),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function failuresCandidates(Request $request): JsonResponse
    {
        $since = $this->parsePeriod($request);
        $search = $this->parseSearch($request);
        [, $queue, $connection, $category] = $this->parseFilters($request);

        $limit = $this->candidateLimit();

        $ids = $this->repository->listFailureUuids(
            $since, $search, $queue, $connection, $category, $limit,
        );
        $total = $this->repository->countFiltered(
            $since, $search, JobStatus::Failed, $queue, $connection, $category,
        );

        return new JsonResponse([
            'ids' => $ids,
            'total' => $total,
            'truncated' => $total > count($ids),
        ]);
    }

    private function candidateLimit(): int
    {
        /** @var mixed $value */
        $value = config('jobs-monitor.bulk.candidate_limit', 10000);

        return is_int($value) && $value > 0 ? $value : 10000;
    }

    public function failures(Request $request): JsonResponse
    {
        $since = $this->parsePeriod($request);
        $search = $this->parseSearch($request);
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min((int) $request->query('per_page', '10'), 200);
        [$sortBy, $sortDir] = $this->parseSort($request);
        [, $queue, $connection, $category] = $this->parseFilters($request);

        $records = $this->repository->findPaginated(
            $since, $search, $perPage, $page, $sortBy, $sortDir,
            JobStatus::Failed, $queue, $connection, $category,
        );
        $total = $this->repository->countFiltered(
            $since, $search, JobStatus::Failed, $queue, $connection, $category,
        );

        return new JsonResponse([
            'data' => array_map([$this, 'serializeRecord'], $records),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function dlq(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min((int) $request->query('per_page', '50'), 200);
        $maxTries = max(1, (int) config('jobs-monitor.max_tries', 3));

        $records = $this->repository->findDeadLetterJobs($perPage, $page, $maxTries);
        $total = $this->repository->countDeadLetterJobs($maxTries);

        return new JsonResponse([
            'data' => array_map([$this, 'serializeRecord'], $records),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'max_tries' => $maxTries,
            ],
        ]);
    }

    public function dlqRetry(Request $request, string $uuid, RetryDeadLetterJobAction $action): JsonResponse
    {
        if (! $this->authorizeDestructive('retry')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        $customPayload = null;

        if ($request->has('payload')) {
            $rawPayload = $request->input('payload');

            if (is_array($rawPayload)) {
                $customPayload = $rawPayload;
            } elseif (is_string($rawPayload) && $rawPayload !== '') {
                try {
                    /** @var array<string|int, mixed> $decoded */
                    $decoded = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    return new JsonResponse([
                        'error' => 'Invalid JSON payload: '.$e->getMessage(),
                    ], 422);
                }

                if (! is_array($decoded)) {
                    return new JsonResponse(['error' => 'Payload must be a JSON object.'], 422);
                }

                $customPayload = $decoded;
            }
        }

        try {
            $newUuid = ($action)(new JobIdentifier($uuid), $customPayload);
        } catch (RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'data' => [
                'original_uuid' => $uuid,
                'new_uuid' => $newUuid,
                'edited' => $customPayload !== null,
            ],
        ], 202);
    }

    public function dlqDelete(string $uuid): JsonResponse
    {
        if (! $this->authorizeDestructive('delete')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        $deleted = $this->repository->deleteByIdentifier(new JobIdentifier($uuid));

        return new JsonResponse([
            'data' => [
                'uuid' => $uuid,
                'deleted' => $deleted,
            ],
        ]);
    }

    public function dlqBulkRetry(
        DlqBulkOperationRequest $request,
        BulkRetryDeadLetterAction $action,
    ): JsonResponse {
        if (! $this->authorizeDestructive('retry')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        return $this->bulkResponse($action($request->identifiers()));
    }

    public function dlqBulkDelete(
        DlqBulkOperationRequest $request,
        BulkDeleteDeadLetterAction $action,
    ): JsonResponse {
        if (! $this->authorizeDestructive('delete')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        return $this->bulkResponse($action($request->identifiers()));
    }

    public function dlqBulkCandidates(): JsonResponse
    {
        $maxTries = max(1, (int) config('jobs-monitor.max_tries', 3));
        $limit = $this->candidateLimit();
        $ids = $this->repository->listDeadLetterUuids($maxTries, $limit);
        $total = $this->repository->countDeadLetterJobs($maxTries);

        return new JsonResponse([
            'ids' => $ids,
            'total' => $total,
            'truncated' => $total > count($ids),
        ]);
    }

    private function bulkResponse(BulkOperationResult $result): JsonResponse
    {
        return new JsonResponse([
            'succeeded' => $result->succeeded,
            'failed' => $result->failed,
            'errors' => (object) $result->errors,
            'total' => $result->total(),
        ]);
    }

    private function authorizeDestructive(string $action): bool
    {
        /** @var string|null $ability */
        $ability = config('jobs-monitor.dlq.authorization');

        if ($ability === null) {
            return auth()->check();
        }

        return Gate::check($ability, $action);
    }

    public function attempts(string $uuid): JsonResponse
    {
        $records = $this->repository->findAllAttempts(new JobIdentifier($uuid));

        return new JsonResponse([
            'data' => array_map([$this, 'serializeRecord'], $records),
            'meta' => [
                'total' => count($records),
            ],
        ]);
    }

    public function statsOverview(Request $request): JsonResponse
    {
        $since = $this->parsePeriod($request);
        $data = $this->repository->aggregateStatsByClassMulti($since);

        $enriched = array_map(static function (array $row): array {
            $row['failure_rate'] = $row['total'] > 0
                ? round($row['failed'] / $row['total'], 4)
                : 0.0;

            return $row;
        }, $data);

        return new JsonResponse([
            'data' => $enriched,
            'meta' => [
                'total' => count($enriched),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        /** @var string|null $jobClass */
        $jobClass = $request->query('job_class');

        if ($jobClass === null || $jobClass === '') {
            return new JsonResponse(
                ['error' => 'The job_class query parameter is required.'],
                422,
            );
        }

        $since = $this->parsePeriod($request);
        $counts = $this->repository->statusCounts($since, $jobClass);

        return new JsonResponse([
            'data' => [
                'job_class' => $jobClass,
                'total' => $counts['total'],
                'processed' => $counts['processed'],
                'failed' => $counts['failed'],
                'success_rate' => $counts['total'] > 0
                    ? round($counts['processed'] / $counts['total'], 4)
                    : 0.0,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $since = $this->parsePeriod($request);
        $search = $this->parseSearch($request);
        [, $queue, $connection, $category] = $this->parseFilters($request);

        $counts = $this->repository->statusCounts($since, $search, $queue, $connection, $category);

        return new JsonResponse([
            'data' => [
                'total' => $counts['total'],
                'processed' => $counts['processed'],
                'failed' => $counts['failed'],
                'processing' => $counts['processing'],
                'success_rate' => $counts['total'] > 0
                    ? round($counts['processed'] / $counts['total'], 4)
                    : 0.0,
            ],
        ]);
    }

    public function timeSeries(Request $request): JsonResponse
    {
        /** @var string $period */
        $period = $request->query('period', '24h');

        if (! is_string($period) || ! isset(self::PERIODS[$period])) {
            $period = '24h';
        }

        [$since, $until, $bucketSize] = $this->timeSeriesWindow($period);

        $rows = $this->repository->aggregateTimeBuckets($since, $bucketSize);
        $buckets = $this->zeroFillTimeSeries($since, $until, $bucketSize, $rows);

        return new JsonResponse([
            'data' => [
                'period' => $period,
                'since' => $since->format('Y-m-d\TH:i:s\Z'),
                'until' => $until->format('Y-m-d\TH:i:s\Z'),
                'bucket_size' => $bucketSize,
                'buckets' => $buckets,
            ],
        ]);
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: 'minute'|'hour'|'day'}
     */
    private function timeSeriesWindow(string $period): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return match ($period) {
            '1m' => [$now->modify('-1 minute'), $now, 'minute'],
            '5m' => [$now->modify('-5 minutes'), $now, 'minute'],
            '30m' => [$now->modify('-30 minutes'), $now, 'minute'],
            '1h' => [$now->modify('-1 hour'), $now, 'minute'],
            '6h' => [$now->modify('-6 hours'), $now, 'hour'],
            '24h' => [$now->modify('-24 hours'), $now, 'hour'],
            '7d' => [$now->modify('-7 days'), $now, 'day'],
            '30d' => [$now->modify('-30 days'), $now, 'day'],
            'all' => [$now->modify('-90 days'), $now, 'day'],
            default => [$now->modify('-24 hours'), $now, 'hour'],
        };
    }

    /**
     * Produce a dense list of bucket rows by snapping [$since, $until] to
     * bucket boundaries and filling missing slots with zeros.
     *
     * @param  'minute'|'hour'|'day'  $bucketSize
     * @param  list<array{bucket: string, processed: int, failed: int}>  $rows
     * @return list<array{t: string, processed: int, failed: int}>
     */
    private function zeroFillTimeSeries(
        \DateTimeImmutable $since,
        \DateTimeImmutable $until,
        string $bucketSize,
        array $rows,
    ): array {
        [$truncate, $step] = match ($bucketSize) {
            'minute' => ['Y-m-d\TH:i:00\Z', '+1 minute'],
            'hour' => ['Y-m-d\TH:00:00\Z', '+1 hour'],
            'day' => ['Y-m-d\T00:00:00\Z', '+1 day'],
        };

        $utc = new \DateTimeZone('UTC');

        $byBucket = [];
        foreach ($rows as $row) {
            $byBucket[$row['bucket']] = $row;
        }

        $current = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s\Z',
            $since->setTimezone($utc)->format($truncate),
            $utc,
        );
        $end = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s\Z',
            $until->setTimezone($utc)->format($truncate),
            $utc,
        );

        if ($current === false || $end === false) {
            return [];
        }

        $filled = [];
        while ($current <= $end) {
            $label = $current->format($truncate);
            $filled[] = [
                't' => $label,
                'processed' => (int) ($byBucket[$label]['processed'] ?? 0),
                'failed' => (int) ($byBucket[$label]['failed'] ?? 0),
            ];
            $current = $current->modify($step);
        }

        return $filled;
    }

    private function parsePeriod(Request $request): ?\DateTimeImmutable
    {
        /** @var string $period */
        $period = $request->query('period', '24h');

        if (! is_string($period) || $period === 'all' || ! isset(self::PERIODS[$period])) {
            return null;
        }

        return new \DateTimeImmutable('-'.self::PERIODS[$period]);
    }

    private function parseSearch(Request $request): ?string
    {
        /** @var string|null $search */
        $search = $request->query('search');

        return is_string($search) && $search !== '' ? $search : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseSort(Request $request): array
    {
        $sortBy = $request->query('sort', 'started_at');
        $sortDir = $request->query('dir', 'desc');

        return [
            is_string($sortBy) ? $sortBy : 'started_at',
            is_string($sortDir) ? $sortDir : 'desc',
        ];
    }

    /**
     * @return array{0: ?JobStatus, 1: ?string, 2: ?string, 3: ?FailureCategory}
     */
    private function parseFilters(Request $request): array
    {
        $status = $request->query('status');
        $queue = $request->query('queue');
        $connection = $request->query('connection');
        $category = $request->query('failure_category');

        return [
            is_string($status) ? JobStatus::tryFrom($status) : null,
            is_string($queue) && $queue !== '' ? $queue : null,
            is_string($connection) && $connection !== '' ? $connection : null,
            is_string($category) ? FailureCategory::tryFrom($category) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecord(JobRecord $record): array
    {
        return [
            'uuid' => $record->id->value,
            'attempt' => $record->attempt->value,
            'job_class' => $record->jobClass,
            'connection' => $record->connection,
            'queue' => $record->queue->value,
            'status' => $record->status()->value,
            'started_at' => $record->startedAt->format('c'),
            'finished_at' => $record->finishedAt()?->format('c'),
            'duration_ms' => $record->duration()?->milliseconds,
            'exception' => $record->exception(),
            'failure_category' => $record->failureCategory()?->value,
            'payload' => $record->payload() !== null ? $this->redactor->redact($record->payload()) : null,
        ];
    }
}
