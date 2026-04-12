<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\Repository;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\JobRecordRepositoryContractTests;

final class InMemoryJobRecordRepositoryTest extends TestCase
{
    use JobRecordRepositoryContractTests;

    protected function createRepository(): JobRecordRepository
    {
        return new InMemoryJobRecordRepository;
    }
}
