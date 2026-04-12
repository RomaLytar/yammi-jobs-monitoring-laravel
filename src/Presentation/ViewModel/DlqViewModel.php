<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/** @internal */
final class DlqViewModel
{
    private const PER_PAGE = 50;

    /**
     * @param  list<array<string, mixed>>  $jobs
     */
    public function __construct(
        public readonly array $jobs,
        public readonly int $total,
        public readonly int $page,
        public readonly int $lastPage,
        public readonly int $maxTries,
        public readonly bool $retryEnabled,
    ) {}

    public static function fromRepository(
        JobRecordRepository $repository,
        int $page,
        int $maxTries,
        bool $retryEnabled,
        ?PayloadRedactor $redactor = null,
    ): self {
        $total = $repository->countDeadLetterJobs($maxTries);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $lastPage);

        $records = $repository->findDeadLetterJobs(self::PER_PAGE, $page, $maxTries);

        return new self(
            jobs: array_map(static fn (JobRecord $r) => self::formatRecord($r, $redactor), $records),
            total: $total,
            page: $page,
            lastPage: $lastPage,
            maxTries: $maxTries,
            retryEnabled: $retryEnabled,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatRecord(JobRecord $record, ?PayloadRedactor $redactor = null): array
    {
        $payload = $record->payload();

        if ($payload !== null && $redactor !== null) {
            $payload = $redactor->redact($payload);
        }

        return [
            'uuid' => $record->id->value,
            'attempt' => $record->attempt->value,
            'job_class' => $record->jobClass,
            'short_class' => self::shortClass($record->jobClass),
            'connection' => $record->connection,
            'queue' => $record->queue->value,
            'started_at' => $record->startedAt->format('Y-m-d H:i:s'),
            'finished_at' => $record->finishedAt()?->format('Y-m-d H:i:s'),
            'exception' => $record->exception(),
            'failure_category' => $record->failureCategory()?->value,
            'failure_category_label' => $record->failureCategory()?->label(),
            'payload' => $payload,
            'has_payload' => $payload !== null,
        ];
    }

    private static function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
