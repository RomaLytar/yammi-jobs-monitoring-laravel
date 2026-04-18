<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Failure\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Exception\InvalidFailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

final class FailureGroupTest extends TestCase
{
    private function makeGroup(
        ?int $occurrences = null,
        ?DateTimeImmutable $firstSeenAt = null,
        ?DateTimeImmutable $lastSeenAt = null,
        ?array $affectedJobClasses = null,
    ): FailureGroup {
        return new FailureGroup(
            fingerprint: new FailureFingerprint('a3f1b2c4d5e6f708'),
            firstSeenAt: $firstSeenAt ?? new DateTimeImmutable('2026-01-01 12:00:00'),
            lastSeenAt: $lastSeenAt ?? new DateTimeImmutable('2026-01-01 12:00:00'),
            occurrences: $occurrences ?? 1,
            affectedJobClasses: $affectedJobClasses ?? ['App\\Jobs\\OrderJob'],
            lastJobId: new JobIdentifier('550e8400-e29b-41d4-a716-446655440000'),
            sampleExceptionClass: 'App\\Exceptions\\Boom',
            sampleMessage: 'boom',
            sampleStackTrace: '#0 app/Jobs/OrderJob.php(42)',
        );
    }

    public function test_valid_group_exposes_fields(): void
    {
        $group = $this->makeGroup();

        self::assertSame('a3f1b2c4d5e6f708', $group->fingerprint()->hash);
        self::assertSame(1, $group->occurrences());
        self::assertSame(['App\\Jobs\\OrderJob'], $group->affectedJobClasses());
        self::assertSame('App\\Exceptions\\Boom', $group->sampleExceptionClass());
        self::assertSame('boom', $group->sampleMessage());
        self::assertSame('#0 app/Jobs/OrderJob.php(42)', $group->sampleStackTrace());
    }

    public function test_zero_occurrences_is_rejected(): void
    {
        $this->expectException(InvalidFailureGroup::class);

        $this->makeGroup(occurrences: 0);
    }

    public function test_negative_occurrences_is_rejected(): void
    {
        $this->expectException(InvalidFailureGroup::class);

        $this->makeGroup(occurrences: -1);
    }

    public function test_last_seen_before_first_seen_is_rejected(): void
    {
        $this->expectException(InvalidFailureGroup::class);

        $this->makeGroup(
            firstSeenAt: new DateTimeImmutable('2026-01-02'),
            lastSeenAt: new DateTimeImmutable('2026-01-01'),
        );
    }

    public function test_empty_affected_classes_is_rejected(): void
    {
        $this->expectException(InvalidFailureGroup::class);

        $this->makeGroup(affectedJobClasses: []);
    }

    public function test_duplicate_affected_classes_are_deduplicated_on_construction(): void
    {
        $group = $this->makeGroup(
            affectedJobClasses: ['App\\Jobs\\A', 'App\\Jobs\\A', 'App\\Jobs\\B'],
        );

        self::assertSame(['App\\Jobs\\A', 'App\\Jobs\\B'], $group->affectedJobClasses());
    }

    public function test_record_occurrence_increments_count(): void
    {
        $group = $this->makeGroup();

        $group->recordOccurrence(
            seenAt: new DateTimeImmutable('2026-01-01 12:05:00'),
            jobClass: 'App\\Jobs\\OrderJob',
            jobId: new JobIdentifier('11111111-2222-3333-4444-555555555555'),
        );

        self::assertSame(2, $group->occurrences());
    }

    public function test_record_occurrence_updates_last_seen_at(): void
    {
        $group = $this->makeGroup();
        $newSeen = new DateTimeImmutable('2026-01-01 13:00:00');

        $group->recordOccurrence(
            seenAt: $newSeen,
            jobClass: 'App\\Jobs\\OrderJob',
            jobId: new JobIdentifier('11111111-2222-3333-4444-555555555555'),
        );

        self::assertEquals($newSeen, $group->lastSeenAt());
    }

    public function test_record_occurrence_adds_new_job_class(): void
    {
        $group = $this->makeGroup();

        $group->recordOccurrence(
            seenAt: new DateTimeImmutable('2026-01-01 12:05:00'),
            jobClass: 'App\\Jobs\\OtherJob',
            jobId: new JobIdentifier('11111111-2222-3333-4444-555555555555'),
        );

        self::assertSame(
            ['App\\Jobs\\OrderJob', 'App\\Jobs\\OtherJob'],
            $group->affectedJobClasses(),
        );
    }

    public function test_record_occurrence_does_not_duplicate_existing_job_class(): void
    {
        $group = $this->makeGroup();

        $group->recordOccurrence(
            seenAt: new DateTimeImmutable('2026-01-01 12:05:00'),
            jobClass: 'App\\Jobs\\OrderJob',
            jobId: new JobIdentifier('11111111-2222-3333-4444-555555555555'),
        );

        self::assertSame(['App\\Jobs\\OrderJob'], $group->affectedJobClasses());
    }

    public function test_record_occurrence_updates_last_job_id(): void
    {
        $group = $this->makeGroup();
        $newJobId = new JobIdentifier('11111111-2222-3333-4444-555555555555');

        $group->recordOccurrence(
            seenAt: new DateTimeImmutable('2026-01-01 12:05:00'),
            jobClass: 'App\\Jobs\\OrderJob',
            jobId: $newJobId,
        );

        self::assertTrue($group->lastJobId()->equals($newJobId));
    }

    public function test_record_occurrence_rejects_seen_at_before_last_seen(): void
    {
        $group = $this->makeGroup(
            firstSeenAt: new DateTimeImmutable('2026-01-01 12:00:00'),
            lastSeenAt: new DateTimeImmutable('2026-01-01 12:30:00'),
        );

        $this->expectException(InvalidFailureGroup::class);

        $group->recordOccurrence(
            seenAt: new DateTimeImmutable('2026-01-01 12:00:00'),
            jobClass: 'App\\Jobs\\OrderJob',
            jobId: new JobIdentifier('11111111-2222-3333-4444-555555555555'),
        );
    }
}
