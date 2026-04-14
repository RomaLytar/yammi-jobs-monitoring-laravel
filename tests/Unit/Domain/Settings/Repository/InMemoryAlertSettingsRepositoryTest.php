<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Settings\Repository;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Tests\Support\AlertSettingsRepositoryContractTests;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;

final class InMemoryAlertSettingsRepositoryTest extends TestCase
{
    use AlertSettingsRepositoryContractTests;

    protected function createRepository(): AlertSettingsRepository
    {
        return new InMemoryAlertSettingsRepository;
    }
}
