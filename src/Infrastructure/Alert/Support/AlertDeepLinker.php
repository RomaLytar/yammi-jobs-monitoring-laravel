<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Support;

use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;

/**
 * Resolves the monitor-UI path that matches an alert trigger so channels
 * can deep-link directly to the page showing the offending rows instead
 * of dropping the operator on the dashboard root.
 *
 * @internal
 */
final class AlertDeepLinker
{
    public function __construct(private readonly ?string $baseUrl) {}

    public function linkFor(AlertTrigger $trigger): ?string
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            return null;
        }

        return rtrim($this->baseUrl, '/').$this->pathFor($trigger);
    }

    /**
     * Trigger → relative path. Exhaustive `match` so a new AlertTrigger
     * case without an entry here fails PHPStan. Entries with a URL
     * fragment (e.g. `#anomalies-partial`) scroll the target block
     * into view on the receiving page.
     */
    private function pathFor(AlertTrigger $trigger): string
    {
        return match ($trigger) {
            AlertTrigger::FailureGroupNew,
            AlertTrigger::FailureGroupBurst => '/failures',
            AlertTrigger::DlqSize,
            AlertTrigger::FailureCategory,
            AlertTrigger::JobClassFailureRate,
            AlertTrigger::FailureRate => '/dlq',
            AlertTrigger::ScheduledTaskFailed => '/scheduled?status=failed',
            AlertTrigger::ScheduledTaskLate => '/scheduled?status=late',
            AlertTrigger::DurationAnomaly => '/anomalies',
            AlertTrigger::PartialCompletion => '/anomalies#anomalies-partial',
            AlertTrigger::ZeroProcessed => '/anomalies#anomalies-silent',
            AlertTrigger::WorkerSilent => '/workers#workers-silent',
            AlertTrigger::WorkerUnderprovisioned => '/workers#workers-coverage',
        };
    }
}
