<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Integration\Settings;

use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentAlertSettingsRepository;
use Yammi\JobsMonitor\Tests\Support\AlertSettingsRepositoryContractTests;
use Yammi\JobsMonitor\Tests\TestCase;

final class EloquentAlertSettingsRepositoryTest extends TestCase
{
    use AlertSettingsRepositoryContractTests;

    protected function createRepository(): AlertSettingsRepository
    {
        return new EloquentAlertSettingsRepository;
    }
}
