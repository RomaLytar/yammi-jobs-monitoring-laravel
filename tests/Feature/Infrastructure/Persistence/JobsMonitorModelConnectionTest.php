<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Persistence;

use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel;
use Yammi\JobsMonitor\Tests\TestCase;

final class JobsMonitorModelConnectionTest extends TestCase
{
    public function test_uses_default_connection_when_config_is_null(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', null);

        self::assertNull((new JobRecordModel)->getConnectionName());
    }

    public function test_uses_configured_connection_when_set(): void
    {
        $this->app['config']->set('jobs-monitor.database.connection', 'custom_db');

        self::assertSame('custom_db', (new JobRecordModel)->getConnectionName());
    }
}
