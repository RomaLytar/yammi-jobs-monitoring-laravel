<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Persistence\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentFailureGroupRepository;
use Yammi\JobsMonitor\Tests\TestCase;

final class EloquentFailureGroupRepositoryTest extends TestCase
{
    private EloquentFailureGroupRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new EloquentFailureGroupRepository;
    }

    public function test_save_then_find_returns_equivalent_group(): void
    {
        $group = $this->makeGroup(hash: 'a3f1b2c4d5e6f708');

        $this->repo->save($group);

        $found = $this->repo->findByFingerprint(new FailureFingerprint('a3f1b2c4d5e6f708'));

        self::assertNotNull($found);
        self::assertTrue($found->fingerprint()->equals($group->fingerprint()));
        self::assertSame($group->occurrences(), $found->occurrences());
        self::assertSame($group->affectedJobClasses(), $found->affectedJobClasses());
        self::assertSame($group->sampleExceptionClass(), $found->sampleExceptionClass());
        self::assertSame($group->sampleMessage(), $found->sampleMessage());
        self::assertSame($group->sampleStackTrace(), $found->sampleStackTrace());
        self::assertTrue($found->lastJobId()->equals($group->lastJobId()));
    }

    public function test_find_returns_null_when_fingerprint_unknown(): void
    {
        self::assertNull(
            $this->repo->findByFingerprint(new FailureFingerprint('0000000000000000')),
        );
    }

    public function test_save_upserts_on_same_fingerprint(): void
    {
        $hash = 'a3f1b2c4d5e6f708';
        $this->repo->save($this->makeGroup(hash: $hash, occurrences: 1));

        $mutated = $this->makeGroup(hash: $hash, occurrences: 5, lastSeenAt: new DateTimeImmutable('2026-01-01 13:00:00'));
        $this->repo->save($mutated);

        $found = $this->repo->findByFingerprint(new FailureFingerprint($hash));

        self::assertNotNull($found);
        self::assertSame(5, $found->occurrences());
        self::assertSame(1, $this->repo->countAll());
    }

    public function test_list_ordered_by_last_seen_returns_most_recent_first(): void
    {
        $older = $this->makeGroup(
            hash: '1111111111111111',
            firstSeenAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            lastSeenAt: new DateTimeImmutable('2026-01-01 10:00:00'),
        );
        $newer = $this->makeGroup(
            hash: '2222222222222222',
            firstSeenAt: new DateTimeImmutable('2026-01-01 11:00:00'),
            lastSeenAt: new DateTimeImmutable('2026-01-01 11:00:00'),
        );

        $this->repo->save($older);
        $this->repo->save($newer);

        $list = $this->repo->listOrderedByLastSeen(limit: 10, offset: 0);

        self::assertCount(2, $list);
        self::assertSame('2222222222222222', $list[0]->fingerprint()->hash);
        self::assertSame('1111111111111111', $list[1]->fingerprint()->hash);
    }

    public function test_list_honours_limit_and_offset(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->save($this->makeGroup(
                hash: str_pad((string) ($i + 1), 16, '0', STR_PAD_LEFT),
                firstSeenAt: new DateTimeImmutable("2026-01-01 1{$i}:00:00"),
                lastSeenAt: new DateTimeImmutable("2026-01-01 1{$i}:00:00"),
            ));
        }

        $page1 = $this->repo->listOrderedByLastSeen(limit: 2, offset: 0);
        $page2 = $this->repo->listOrderedByLastSeen(limit: 2, offset: 2);

        self::assertCount(2, $page1);
        self::assertCount(1, $page2);
        self::assertNotSame($page1[0]->fingerprint()->hash, $page2[0]->fingerprint()->hash);
    }

    public function test_first_seen_since_filters_by_first_seen_at(): void
    {
        $this->repo->save($this->makeGroup(
            hash: '1111111111111111',
            firstSeenAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            lastSeenAt: new DateTimeImmutable('2026-01-01 10:00:00'),
        ));
        $this->repo->save($this->makeGroup(
            hash: '2222222222222222',
            firstSeenAt: new DateTimeImmutable('2026-01-01 12:00:00'),
            lastSeenAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        ));

        $result = $this->repo->firstSeenSince(new DateTimeImmutable('2026-01-01 11:00:00'));

        self::assertCount(1, $result);
        self::assertSame('2222222222222222', $result[0]->fingerprint()->hash);
    }

    private function makeGroup(
        string $hash = 'a3f1b2c4d5e6f708',
        ?int $occurrences = null,
        ?DateTimeImmutable $firstSeenAt = null,
        ?DateTimeImmutable $lastSeenAt = null,
    ): FailureGroup {
        $first = $firstSeenAt ?? new DateTimeImmutable('2026-01-01 12:00:00');
        $last = $lastSeenAt ?? $first;

        return new FailureGroup(
            fingerprint: new FailureFingerprint($hash),
            firstSeenAt: $first,
            lastSeenAt: $last,
            occurrences: $occurrences ?? 1,
            affectedJobClasses: ['App\\Jobs\\OrderJob'],
            lastJobId: new JobIdentifier('550e8400-e29b-41d4-a716-446655440000'),
            sampleExceptionClass: 'App\\Exceptions\\Boom',
            sampleMessage: 'something went wrong',
            sampleStackTrace: '#0 app/Jobs/OrderJob.php(42)',
        );
    }
}
