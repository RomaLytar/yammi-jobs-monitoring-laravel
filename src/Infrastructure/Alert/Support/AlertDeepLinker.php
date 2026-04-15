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
    /**
     * Trigger → relative path (no leading slash handling; caller joins
     * with the base URL). Entries with a relative fragment (e.g.
     * `/anomalies#anomalies-partial`) scroll the target block into view.
     *
     * @var array<string, string>
     */
    private const PATHS = [
        AlertTrigger::FailureGroupNew->value => '/failures',
        AlertTrigger::FailureGroupBurst->value => '/failures',
        AlertTrigger::DlqSize->value => '/dlq',
        AlertTrigger::ScheduledTaskFailed->value => '/scheduled?status=failed',
        AlertTrigger::ScheduledTaskLate->value => '/scheduled?status=late',
        AlertTrigger::DurationAnomaly->value => '/anomalies',
        AlertTrigger::PartialCompletion->value => '/anomalies#anomalies-partial',
        AlertTrigger::ZeroProcessed->value => '/anomalies#anomalies-silent',
        AlertTrigger::FailureCategory->value => '/dlq',
        AlertTrigger::JobClassFailureRate->value => '/dlq',
        AlertTrigger::FailureRate->value => '/dlq',
    ];

    public function __construct(private readonly ?string $baseUrl) {}

    public function linkFor(AlertTrigger $trigger): ?string
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            return null;
        }

        return rtrim($this->baseUrl, '/').self::PATHS[$trigger->value];
    }
}
