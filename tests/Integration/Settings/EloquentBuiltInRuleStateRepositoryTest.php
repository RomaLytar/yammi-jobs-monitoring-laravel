<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Settings;

use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\BuiltInRuleStateRepositoryContractTests;
use Yammi\JobsMonitor\Tests\TestCase;

final class EloquentBuiltInRuleStateRepositoryTest extends TestCase
{
    use BuiltInRuleStateRepositoryContractTests;

    protected function createBuiltInStateRepository(): BuiltInRuleStateRepository
    {
        return new EloquentBuiltInRuleStateRepository;
    }
}
