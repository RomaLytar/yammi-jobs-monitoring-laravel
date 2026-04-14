<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Settings\Repository;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\BuiltInRuleStateRepositoryContractTests;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;

final class InMemoryBuiltInRuleStateRepositoryTest extends TestCase
{
    use BuiltInRuleStateRepositoryContractTests;

    protected function createBuiltInStateRepository(): BuiltInRuleStateRepository
    {
        return new InMemoryBuiltInRuleStateRepository;
    }
}
