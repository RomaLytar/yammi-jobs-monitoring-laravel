<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Settings\Repository;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;
use Yammi\JobsMonitor\Tests\Support\ManagedAlertRuleRepositoryContractTests;

final class InMemoryManagedAlertRuleRepositoryTest extends TestCase
{
    use ManagedAlertRuleRepositoryContractTests;

    protected function createRuleRepository(): ManagedAlertRuleRepository
    {
        return new InMemoryManagedAlertRuleRepository;
    }
}
