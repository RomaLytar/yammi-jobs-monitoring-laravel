<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\OutcomeReport;

final class CaptureOutcomeReportAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
    ) {}

    public function __invoke(string $uuid, int $attempt, OutcomeReport $report): void
    {
        $this->repository->recordOutcome(
            new JobIdentifier($uuid),
            new Attempt($attempt),
            $report,
        );
    }
}
