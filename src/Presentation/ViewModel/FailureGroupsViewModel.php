<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel;

use Yammi\JobsMonitor\Domain\Failure\Contract\TraceRedactor;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;

/** @internal */
final class FailureGroupsViewModel
{
    private const PER_PAGE = 50;

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    public function __construct(
        public readonly array $groups,
        public readonly int $total,
        public readonly int $page,
        public readonly int $lastPage,
    ) {}

    public static function fromRepository(
        FailureGroupRepository $repository,
        int $page,
        ?TraceRedactor $redactor = null,
    ): self {
        $total = $repository->countAll();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $lastPage);

        $items = $repository->listOrderedByLastSeen(
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE,
        );

        return new self(
            groups: array_map(static fn (FailureGroup $g) => self::formatGroup($g, $redactor), $items),
            total: $total,
            page: $page,
            lastPage: $lastPage,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatGroup(FailureGroup $g, ?TraceRedactor $redactor): array
    {
        $message = $g->sampleMessage();
        $trace = $g->sampleStackTrace();

        if ($redactor !== null) {
            $message = $redactor->redact($message);
            $trace = $redactor->redact($trace);
        }

        return [
            'fingerprint' => $g->fingerprint()->hash,
            'occurrences' => $g->occurrences(),
            'affected_job_classes' => $g->affectedJobClasses(),
            'first_seen_at' => $g->firstSeenAt()->format('Y-m-d H:i:s'),
            'last_seen_at' => $g->lastSeenAt()->format('Y-m-d H:i:s'),
            'sample_exception_class' => $g->sampleExceptionClass(),
            'sample_exception_short' => self::shortClass($g->sampleExceptionClass()),
            'sample_message' => $message,
            'sample_stack_trace' => $trace,
            'last_job_uuid' => $g->lastJobId()->value,
        ];
    }

    private static function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
