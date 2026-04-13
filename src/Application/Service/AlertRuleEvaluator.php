<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\FailureSample;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/**
 * Pure evaluation logic: given an AlertRule and the current time,
 * returns an AlertPayload when the rule has tripped or null otherwise.
 *
 * Stateless and side-effect-free beyond repository reads.
 */
final class AlertRuleEvaluator
{
    private const SAMPLE_LIMIT = 5;

    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly int $maxTries,
    ) {}

    public function evaluate(AlertRule $rule, DateTimeImmutable $now): ?AlertPayload
    {
        $count = $this->countForRule($rule, $now);

        return $this->payloadIfTripped($rule, $count, $now);
    }

    private function countForRule(AlertRule $rule, DateTimeImmutable $now): int
    {
        return match ($rule->trigger) {
            AlertTrigger::FailureRate => $this->repository
                ->countFailuresSince($this->windowStart($rule, $now), $rule->minAttempt),
            AlertTrigger::FailureCategory => $this->repository
                ->countFailuresByCategorySince(
                    FailureCategory::from((string) $rule->triggerValue),
                    $this->windowStart($rule, $now),
                    $rule->minAttempt,
                ),
            AlertTrigger::JobClassFailureRate => $this->repository
                ->countFailuresByClassSince(
                    (string) $rule->triggerValue,
                    $this->windowStart($rule, $now),
                    $rule->minAttempt,
                ),
            AlertTrigger::DlqSize => $this->repository
                ->countDeadLetterJobs($this->maxTries),
        };
    }

    private function payloadIfTripped(AlertRule $rule, int $count, DateTimeImmutable $now): ?AlertPayload
    {
        if ($count < $rule->threshold) {
            return null;
        }

        return new AlertPayload(
            trigger: $rule->trigger,
            subject: $this->subjectFor($rule),
            body: $this->bodyFor($rule, $count),
            context: $this->contextFor($rule, $count) + $this->attemptContext($rule),
            triggeredAt: $now,
            recentFailures: $this->samplesFor($rule, $now),
        );
    }

    /**
     * @return list<FailureSample>
     */
    private function samplesFor(AlertRule $rule, DateTimeImmutable $now): array
    {
        $records = match ($rule->trigger) {
            AlertTrigger::FailureRate => $this->repository->findFailureSamples(
                $this->windowStart($rule, $now), self::SAMPLE_LIMIT, $rule->minAttempt,
            ),
            AlertTrigger::FailureCategory => $this->repository->findFailureSamples(
                $this->windowStart($rule, $now), self::SAMPLE_LIMIT, $rule->minAttempt,
                FailureCategory::from((string) $rule->triggerValue),
            ),
            AlertTrigger::JobClassFailureRate => $this->repository->findFailureSamples(
                $this->windowStart($rule, $now), self::SAMPLE_LIMIT, $rule->minAttempt,
                null, (string) $rule->triggerValue,
            ),
            AlertTrigger::DlqSize => $this->repository->findDeadLetterJobs(
                self::SAMPLE_LIMIT, 1, $this->maxTries,
            ),
        };

        return array_map(
            fn (JobRecord $r) => new FailureSample(
                uuid: $r->id->value,
                attempt: $r->attempt->value,
                jobClass: $r->jobClass,
                exception: $r->exception(),
                failedAt: $r->finishedAt() ?? $r->startedAt,
            ),
            $records,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptContext(AlertRule $rule): array
    {
        return $rule->minAttempt === null ? [] : ['min_attempt' => $rule->minAttempt];
    }

    private function subjectFor(AlertRule $rule): string
    {
        return match ($rule->trigger) {
            AlertTrigger::FailureRate => 'Failure rate threshold reached',
            AlertTrigger::FailureCategory => sprintf(
                '%s failures detected',
                FailureCategory::from((string) $rule->triggerValue)->label(),
            ),
            AlertTrigger::JobClassFailureRate => sprintf(
                'Job class failure rate reached: %s',
                (string) $rule->triggerValue,
            ),
            AlertTrigger::DlqSize => 'Dead-letter queue size threshold reached',
        };
    }

    private function bodyFor(AlertRule $rule, int $count): string
    {
        return match ($rule->trigger) {
            AlertTrigger::FailureRate => sprintf(
                '%d failures in the last %s (threshold: %d).',
                $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::FailureCategory => sprintf(
                '%d %s failures in the last %s (threshold: %d).',
                $count, (string) $rule->triggerValue, $rule->window, $rule->threshold,
            ),
            AlertTrigger::JobClassFailureRate => sprintf(
                '%s has %d failures in the last %s (threshold: %d).',
                (string) $rule->triggerValue, $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::DlqSize => sprintf(
                'DLQ contains %d jobs (threshold: %d).',
                $count, $rule->threshold,
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFor(AlertRule $rule, int $count): array
    {
        $base = [
            'count' => $count,
            'threshold' => $rule->threshold,
        ];

        return match ($rule->trigger) {
            AlertTrigger::FailureRate => $base + [
                'window' => $rule->window,
            ],
            AlertTrigger::FailureCategory => $base + [
                'window' => $rule->window,
                'category' => (string) $rule->triggerValue,
            ],
            AlertTrigger::JobClassFailureRate => $base + [
                'window' => $rule->window,
                'job_class' => (string) $rule->triggerValue,
            ],
            AlertTrigger::DlqSize => $base,
        };
    }

    private function windowStart(AlertRule $rule, DateTimeImmutable $now): DateTimeImmutable
    {
        $seconds = $rule->windowSeconds();

        return $now->modify("-{$seconds} seconds");
    }
}
