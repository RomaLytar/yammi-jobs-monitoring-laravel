<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\Entity;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Failure\Exception\InvalidFailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

final class FailureGroup
{
    private int $occurrences;

    private DateTimeImmutable $lastSeenAt;

    /** @var list<string> */
    private array $affectedJobClasses;

    private JobIdentifier $lastJobId;

    /**
     * @param  list<string>  $affectedJobClasses
     */
    public function __construct(
        private readonly FailureFingerprint $fingerprint,
        private readonly DateTimeImmutable $firstSeenAt,
        DateTimeImmutable $lastSeenAt,
        int $occurrences,
        array $affectedJobClasses,
        JobIdentifier $lastJobId,
        private readonly string $sampleExceptionClass,
        private readonly string $sampleMessage,
        private readonly string $sampleStackTrace,
    ) {
        $this->guardOccurrences($occurrences);
        $this->guardTimeOrdering($firstSeenAt, $lastSeenAt);
        $this->guardAffectedClasses($affectedJobClasses);

        $this->occurrences = $occurrences;
        $this->lastSeenAt = $lastSeenAt;
        $this->affectedJobClasses = array_values(array_unique($affectedJobClasses));
        $this->lastJobId = $lastJobId;
    }

    public function recordOccurrence(
        DateTimeImmutable $seenAt,
        string $jobClass,
        JobIdentifier $jobId,
    ): void {
        if ($seenAt < $this->lastSeenAt) {
            throw new InvalidFailureGroup(sprintf(
                'recordOccurrence: seenAt (%s) cannot be earlier than lastSeenAt (%s).',
                $seenAt->format(DATE_ATOM),
                $this->lastSeenAt->format(DATE_ATOM),
            ));
        }

        $this->occurrences++;
        $this->lastSeenAt = $seenAt;
        $this->lastJobId = $jobId;

        if (! in_array($jobClass, $this->affectedJobClasses, true)) {
            $this->affectedJobClasses[] = $jobClass;
        }
    }

    public function fingerprint(): FailureFingerprint
    {
        return $this->fingerprint;
    }

    public function firstSeenAt(): DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function lastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function occurrences(): int
    {
        return $this->occurrences;
    }

    /**
     * @return list<string>
     */
    public function affectedJobClasses(): array
    {
        return $this->affectedJobClasses;
    }

    public function lastJobId(): JobIdentifier
    {
        return $this->lastJobId;
    }

    public function sampleExceptionClass(): string
    {
        return $this->sampleExceptionClass;
    }

    public function sampleMessage(): string
    {
        return $this->sampleMessage;
    }

    public function sampleStackTrace(): string
    {
        return $this->sampleStackTrace;
    }

    private function guardOccurrences(int $occurrences): void
    {
        if ($occurrences < 1) {
            throw new InvalidFailureGroup(sprintf(
                'occurrences must be >= 1, got %d.',
                $occurrences,
            ));
        }
    }

    private function guardTimeOrdering(DateTimeImmutable $firstSeenAt, DateTimeImmutable $lastSeenAt): void
    {
        if ($lastSeenAt < $firstSeenAt) {
            throw new InvalidFailureGroup(sprintf(
                'lastSeenAt (%s) cannot be earlier than firstSeenAt (%s).',
                $lastSeenAt->format(DATE_ATOM),
                $firstSeenAt->format(DATE_ATOM),
            ));
        }
    }

    /**
     * @param  list<string>  $affectedJobClasses
     */
    private function guardAffectedClasses(array $affectedJobClasses): void
    {
        if ($affectedJobClasses === []) {
            throw new InvalidFailureGroup('affectedJobClasses must contain at least one entry.');
        }
    }
}
