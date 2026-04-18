<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\FailureSample;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;

/**
 * Pure evaluation logic: given an AlertRule and the current time,
 * returns an AlertPayload when the rule has tripped or null otherwise.
 *
 * Stateless and side-effect-free beyond repository reads.
 */
final class AlertRuleEvaluator
{
    private const SAMPLE_LIMIT = 10;

    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly FailureGroupRepository $groups,
        private readonly int $maxTries,
        private readonly ?ScheduledTaskRunRepository $scheduledRuns = null,
        private readonly ?DurationBaselineRepository $durationBaselines = null,
    ) {}

    /**
     * Returns zero, one, or many payloads depending on the rule type.
     * Group-based rules can fan out to one payload per matching group.
     *
     * @return list<AlertPayload>
     */
    public function evaluate(AlertRule $rule, DateTimeImmutable $now): array
    {
        if ($rule->trigger === AlertTrigger::FailureGroupBurst) {
            return $this->evaluateGroupBurst($rule, $now);
        }

        $count = $this->countForRule($rule, $now);
        $payload = $this->payloadIfTripped($rule, $count, $now);

        return $payload === null ? [] : [$payload];
    }

    /**
     * Per-group: emits one payload per group whose recent failure count
     * crossed the rule's threshold inside the rule's window.
     *
     * @return list<AlertPayload>
     */
    private function evaluateGroupBurst(AlertRule $rule, DateTimeImmutable $now): array
    {
        $counts = $this->repository->countFailuresByFingerprintSince(
            $this->windowStart($rule, $now),
            $rule->threshold,
        );

        $payloads = [];
        foreach ($counts as $hash => $count) {
            $group = $this->groups->findByFingerprint(
                new \Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint($hash),
            );

            $payloads[] = new AlertPayload(
                trigger: $rule->trigger,
                subject: $this->burstSubject($group, $hash),
                body: $this->burstBody($count, $rule),
                context: [
                    'count' => $count,
                    'threshold' => $rule->threshold,
                    'window' => $rule->window,
                    'fingerprint' => $hash,
                    'sample_exception_class' => $group?->sampleExceptionClass(),
                    'sample_message' => $group?->sampleMessage(),
                    'occurrences' => $group?->occurrences(),
                ],
                triggeredAt: $now,
                fingerprint: $hash,
            );
        }

        return $payloads;
    }

    private function burstSubject(?FailureGroup $group, string $hash): string
    {
        $excerpt = $group !== null
            ? sprintf('%s — %s', self::shortClass($group->sampleExceptionClass()), $group->sampleMessage())
            : $hash;

        return sprintf('Failure group bursting: %s', $excerpt);
    }

    private function burstBody(int $count, AlertRule $rule): string
    {
        return sprintf(
            '%d failures in this group in the last %s (threshold: %d).',
            $count,
            (string) $rule->window,
            $rule->threshold,
        );
    }

    private static function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
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
            AlertTrigger::FailureGroupNew => count(
                $this->groups->firstSeenSince($this->windowStart($rule, $now)),
            ),
            AlertTrigger::FailureGroupBurst => 0,
            AlertTrigger::ScheduledTaskFailed => $this->scheduledRuns?->countFailedSince(
                $this->windowStart($rule, $now)
            ) ?? 0,
            AlertTrigger::ScheduledTaskLate => $this->scheduledRuns?->countLateSince(
                $this->windowStart($rule, $now)
            ) ?? 0,
            AlertTrigger::DurationAnomaly => $this->durationBaselines?->countAnomaliesSince(
                $this->windowStart($rule, $now)
            ) ?? 0,
            AlertTrigger::PartialCompletion => $this->repository->countPartialCompletionsSince(
                $this->windowStart($rule, $now)
            ),
            AlertTrigger::ZeroProcessed => $this->repository->countZeroProcessedSince(
                $this->windowStart($rule, $now)
            ),
            // Worker triggers are emitted directly by the heartbeat
            // watchdog command, not through rule evaluation.
            AlertTrigger::WorkerSilent,
            AlertTrigger::WorkerUnderprovisioned => 0,
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
            context: $this->contextFor($rule, $count, $now) + $this->attemptContext($rule),
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
            AlertTrigger::FailureGroupNew,
            AlertTrigger::FailureGroupBurst,
            AlertTrigger::ScheduledTaskFailed,
            AlertTrigger::ScheduledTaskLate,
            AlertTrigger::DurationAnomaly,
            AlertTrigger::PartialCompletion,
            AlertTrigger::ZeroProcessed,
            AlertTrigger::WorkerSilent,
            AlertTrigger::WorkerUnderprovisioned => [],
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
            AlertTrigger::FailureGroupNew => 'New failure groups detected',
            AlertTrigger::FailureGroupBurst => 'Failure group bursting',
            AlertTrigger::ScheduledTaskFailed => 'Scheduled task is failing',
            AlertTrigger::ScheduledTaskLate => 'Scheduled task stuck / running late',
            AlertTrigger::DurationAnomaly => 'Jobs running outside their normal duration envelope',
            AlertTrigger::PartialCompletion => 'Partial-completion failures detected',
            AlertTrigger::ZeroProcessed => 'Jobs succeeding with zero processed items',
            AlertTrigger::WorkerSilent => 'Worker heartbeat missing',
            AlertTrigger::WorkerUnderprovisioned => 'Queue has fewer workers than expected',
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
            AlertTrigger::FailureGroupNew => sprintf(
                '%d new failure groups first seen in the last %s (threshold: %d).',
                $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::FailureGroupBurst => $this->burstBody($count, $rule),
            AlertTrigger::ScheduledTaskFailed => sprintf(
                '%d scheduled-task failure(s) in the last %s (threshold: %d).',
                $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::ScheduledTaskLate => sprintf(
                '%d scheduled-task run(s) flagged as late in the last %s (threshold: %d).',
                $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::DurationAnomaly => sprintf(
                '%d duration anomal(y/ies) detected in the last %s (threshold: %d).',
                $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::PartialCompletion => sprintf(
                '%d partial-completion failure(s) in the last %s (threshold: %d). '
                .'A partial completion is a job that failed after reporting non-zero progress.',
                $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::ZeroProcessed => sprintf(
                '%d job(s) completed successfully but processed zero items in the last %s (threshold: %d).',
                $count, $rule->window, $rule->threshold,
            ),
            AlertTrigger::WorkerSilent,
            AlertTrigger::WorkerUnderprovisioned => 'Emitted by the heartbeat watchdog, not rule-based.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFor(AlertRule $rule, int $count, DateTimeImmutable $now): array
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
            AlertTrigger::FailureGroupNew => $base + [
                'window' => $rule->window,
                'fingerprints' => $this->fingerprintsFor($rule, $now),
            ],
            AlertTrigger::FailureGroupBurst => $base + ['window' => $rule->window],
            AlertTrigger::ScheduledTaskFailed,
            AlertTrigger::ScheduledTaskLate,
            AlertTrigger::DurationAnomaly,
            AlertTrigger::PartialCompletion,
            AlertTrigger::ZeroProcessed => $base + ['window' => $rule->window],
            AlertTrigger::WorkerSilent,
            AlertTrigger::WorkerUnderprovisioned => $base,
        };
    }

    /**
     * @return list<string>
     */
    private function fingerprintsFor(AlertRule $rule, DateTimeImmutable $now): array
    {
        return array_values(array_map(
            static fn (FailureGroup $g): string => $g->fingerprint()->hash,
            $this->groups->firstSeenSince($this->windowStart($rule, $now)),
        ));
    }

    private function windowStart(AlertRule $rule, DateTimeImmutable $now): DateTimeImmutable
    {
        $seconds = $rule->windowSeconds();

        return $now->modify("-{$seconds} seconds");
    }
}
