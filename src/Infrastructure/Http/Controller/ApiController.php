<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;

/** @internal */
final class ApiController extends Controller
{
    public function __construct(
        private readonly JobsMonitorService $service,
    ) {}

    public function jobs(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', '50'), 200);

        $records = $this->service->recentJobs($limit);

        return new JsonResponse([
            'data' => array_map([$this, 'serializeRecord'], $records),
        ]);
    }

    public function failures(Request $request): JsonResponse
    {
        $hours = min((int) $request->query('hours', '24'), 168);

        $records = $this->service->recentFailures($hours);

        return new JsonResponse([
            'data' => array_map([$this, 'serializeRecord'], $records),
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

        $stats = $this->service->stats($jobClass);

        return new JsonResponse([
            'data' => [
                'job_class' => $stats->jobClass,
                'total' => $stats->total,
                'processed' => $stats->processed,
                'failed' => $stats->failed,
                'success_rate' => $stats->successRate,
                'avg_duration_ms' => $stats->avgDurationMs,
            ],
        ]);
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
        ];
    }
}
