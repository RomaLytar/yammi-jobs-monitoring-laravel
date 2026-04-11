<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor;

use Illuminate\Support\ServiceProvider;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentJobRecordRepository;

final class JobsMonitorServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__ . '/../config/jobs-monitor.php';

    private const MIGRATIONS_PATH = __DIR__ . '/../database/migrations';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'jobs-monitor');

        $this->app->bind(JobRecordRepository::class, EloquentJobRecordRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(self::MIGRATIONS_PATH);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [self::CONFIG_PATH => config_path('jobs-monitor.php')],
                'jobs-monitor-config',
            );

            $this->publishes(
                [self::MIGRATIONS_PATH => database_path('migrations')],
                'jobs-monitor-migrations',
            );
        }
    }
}
