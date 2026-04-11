<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\Repository;

use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\JobRecordRepositoryContract;

final class InMemoryJobRecordRepositoryTest extends JobRecordRepositoryContract
{
    protected function createRepository(): JobRecordRepository
    {
        return new InMemoryJobRecordRepository();
    }
}
