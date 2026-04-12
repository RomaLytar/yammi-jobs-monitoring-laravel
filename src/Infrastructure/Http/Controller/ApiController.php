<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

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

        $records = $this->repository->findPaginated($since, $search, $perPage, $page, $sortBy, $sortDir);
        $total = $this->repository->countFiltered($since, $search);

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

    public function failures(Request $request): JsonResponse
    {
        $since = $this->parsePeriod($request);
        $search = $this->parseSearch($request);
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min((int) $request->query('per_page', '10'), 200);
        [$sortBy, $sortDir] = $this->parseSort($request);

        $records = $this->repository->findPaginated($since, $search, $perPage, $page, $sortBy, $sortDir, JobStatus::Failed);
        $total = $this->repository->countFiltered($since, $search, JobStatus::Failed);

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
