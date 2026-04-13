<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Settings;

use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentManagedAlertRuleRepository;
use Yammi\JobsMonitor\Tests\Support\ManagedAlertRuleRepositoryContractTests;
use Yammi\JobsMonitor\Tests\TestCase;

final class EloquentManagedAlertRuleRepositoryTest extends TestCase
{
    use ManagedAlertRuleRepositoryContractTests;

    protected function createRuleRepository(): ManagedAlertRuleRepository
    {
        return new EloquentManagedAlertRuleRepository;
    }
}
