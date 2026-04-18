<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use DateTimeImmutable;
use Throwable;
use Yammi\JobsMonitor\Domain\Failure\Contract\TraceNormalizer;
use Yammi\JobsMonitor\Domain\Failure\Contract\TraceRedactor;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Upserts a FailureGroup for the given exception and backfills the
 * fingerprint onto the attempt row.
 *
 * Same-signature exceptions collapse onto one group; different ones
 * produce different fingerprints. The group accumulates affected job
 * classes and the last job identifier to retry from.
 */
final class RecordFailureFingerprintAction
{
    private const SAMPLE_MESSAGE_MAX_BYTES = 1000;

    private const SAMPLE_STACK_TRACE_MAX_BYTES = 8000;

    public function __construct(
        private readonly TraceNormalizer $normalizer,
        private readonly FailureGroupRepository $groups,
        private readonly JobRecordRepository $jobs,
        private readonly TraceRedactor $redactor,
    ) {}

    public function __invoke(
        string $id,
        int $attempt,
        string $jobClass,
        Throwable $exception,
        DateTimeImmutable $occurredAt,
    ): FailureFingerprint {
        $jobId = new JobIdentifier($id);
        $attemptVo = new Attempt($attempt);

        $fingerprint = $this->computeFingerprint($exception);

        $this->upsertGroup($fingerprint, $exception, $jobClass, $jobId, $occurredAt);

        $this->jobs->setFingerprint($jobId, $attemptVo, $fingerprint);

        return $fingerprint;
    }

    private function computeFingerprint(Throwable $exception): FailureFingerprint
    {
        $normalized = $this->normalizer->normalize(
            exceptionClass: $exception::class,
            message: $exception->getMessage(),
            stackTraceAsString: $exception->getTraceAsString(),
        );

        $hash = substr(hash('sha256', $normalized->signature()), 0, 16);

        return new FailureFingerprint($hash);
    }

    private function upsertGroup(
        FailureFingerprint $fingerprint,
        Throwable $exception,
        string $jobClass,
        JobIdentifier $jobId,
        DateTimeImmutable $occurredAt,
    ): void {
        $existing = $this->groups->findByFingerprint($fingerprint);

        if ($existing === null) {
            $this->groups->save(new FailureGroup(
                fingerprint: $fingerprint,
                firstSeenAt: $occurredAt,
                lastSeenAt: $occurredAt,
                occurrences: 1,
                affectedJobClasses: [$jobClass],
                lastJobId: $jobId,
                sampleExceptionClass: $exception::class,
                sampleMessage: $this->truncate(
                    $this->redactor->redact($exception->getMessage()),
                    self::SAMPLE_MESSAGE_MAX_BYTES,
                ),
                sampleStackTrace: $this->truncate(
                    $this->redactor->redact($exception->getTraceAsString()),
                    self::SAMPLE_STACK_TRACE_MAX_BYTES,
                ),
            ));

            return;
        }

        $existing->recordOccurrence($occurredAt, $jobClass, $jobId);
        $this->groups->save($existing);
    }

    private function truncate(string $value, int $maxBytes): string
    {
        return strlen($value) <= $maxBytes ? $value : substr($value, 0, $maxBytes);
    }
}
