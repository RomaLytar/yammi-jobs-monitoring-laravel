<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * In-memory test double for the JobRecordRepository contract. Used by
 * unit tests of Application actions and as the contract verification
 * target for the InMemoryJobRecordRepositoryTest.
 */
final class InMemoryJobRecordRepository implements JobRecordRepository
{
    /**
     * @var array<string, JobRecord>
     */
    private array $records = [];

    public function save(JobRecord $record): void
    {
        $this->records[$this->key($record->id, $record->attempt)] = $record;
    }

    public function findByIdentifierAndAttempt(
        JobIdentifier $id,
        Attempt $attempt,
    ): ?JobRecord {
        return $this->records[$this->key($id, $attempt)] ?? null;
    }

    private function key(JobIdentifier $id, Attempt $attempt): string
    {
        return $id->value . '#' . $attempt->value;
    }
}
