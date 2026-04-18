<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;

final class InMemoryFailureGroupRepository implements FailureGroupRepository
{
    /**
     * @var array<string, FailureGroup>
     */
    private array $groups = [];

    public function findByFingerprint(FailureFingerprint $fingerprint): ?FailureGroup
    {
        return $this->groups[$fingerprint->hash] ?? null;
    }

    public function save(FailureGroup $group): void
    {
        $this->groups[$group->fingerprint()->hash] = $group;
    }

    public function listOrderedByLastSeen(int $limit, int $offset): array
    {
        $sorted = array_values($this->groups);
        usort(
            $sorted,
            static fn (FailureGroup $a, FailureGroup $b) => $b->lastSeenAt() <=> $a->lastSeenAt(),
        );

        return array_slice($sorted, $offset, $limit);
    }

    public function countAll(): int
    {
        return count($this->groups);
    }

    public function firstSeenSince(DateTimeImmutable $since): array
    {
        $result = array_values(array_filter(
            $this->groups,
            static fn (FailureGroup $g) => $g->firstSeenAt() >= $since,
        ));

        usort(
            $result,
            static fn (FailureGroup $a, FailureGroup $b) => $b->firstSeenAt() <=> $a->firstSeenAt(),
        );

        return $result;
    }
}
