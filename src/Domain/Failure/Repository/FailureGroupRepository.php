<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\Repository;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;

interface FailureGroupRepository
{
    public function findByFingerprint(FailureFingerprint $fingerprint): ?FailureGroup;

    public function save(FailureGroup $group): void;

    /**
     * @return list<FailureGroup>
     */
    public function listOrderedByLastSeen(int $limit, int $offset): array;

    public function countAll(): int;

    /**
     * @return list<FailureGroup>
     */
    public function firstSeenSince(DateTimeImmutable $since): array;
}
