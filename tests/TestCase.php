<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Yammi\JobsMonitor\JobsMonitorServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            JobsMonitorServiceProvider::class,
        ];
    }
}
