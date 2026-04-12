<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;

/** @internal */
final class DashboardViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $recentJobs
     * @param  array<int, array<string, mixed>>  $recentFailures
     */
    public function __construct(
        public readonly array $recentJobs,
        public readonly array $recentFailures,
        public readonly int $totalJobs,
        public readonly int $totalFailures,
    ) {}

    public static function fromService(JobsMonitorService $service): self
    {
        $jobs = $service->recentJobs(50);
        $failures = $service->recentFailures(24);

        return new self(
            recentJobs: array_map([self::class, 'formatRecord'], $jobs),
            recentFailures: array_map([self::class, 'formatRecord'], $failures),
            totalJobs: count($jobs),
            totalFailures: count($failures),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatRecord(JobRecord $record): array
    {
        return [
            'uuid' => $record->id->value,
            'attempt' => $record->attempt->value,
            'job_class' => $record->jobClass,
            'short_class' => self::shortClassName($record->jobClass),
            'connection' => $record->connection,
            'queue' => $record->queue->value,
            'status' => $record->status()->value,
            'started_at' => $record->startedAt->format('Y-m-d H:i:s'),
            'finished_at' => $record->finishedAt()?->format('Y-m-d H:i:s'),
            'duration_ms' => $record->duration()?->milliseconds,
            'duration_formatted' => self::formatDuration($record->duration()?->milliseconds),
            'exception' => $record->exception(),
        ];
    }

    private static function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    private static function formatDuration(?int $ms): string
    {
        if ($ms === null) {
            return '—';
        }

        if ($ms < 1000) {
            return number_format($ms).'ms';
        }

        return number_format($ms / 1000, 2).'s';
    }
}
