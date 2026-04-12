<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Persistence\Repository;

use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\JobRecordRepositoryContractTests;
use Yammi\JobsMonitor\Tests\TestCase;

final class EloquentJobRecordRepositoryTest extends TestCase
{
    use JobRecordRepositoryContractTests;

    protected function createRepository(): JobRecordRepository
    {
        return new EloquentJobRecordRepository;
    }
}
