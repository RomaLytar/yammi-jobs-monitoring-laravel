<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Infrastructure\Listener\JobLifecycleSubscriber;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentJobRecordRepository;

final class JobsMonitorServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__.'/../config/jobs-monitor.php';

    private const MIGRATIONS_PATH = __DIR__.'/../database/migrations';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'jobs-monitor');

        $this->app->bind(JobRecordRepository::class, EloquentJobRecordRepository::class);
        $this->app->bind(QueueMetricsDriver::class, NullMetricsDriver::class);
        $this->app->singleton(JobsMonitorService::class);
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

        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        if ((bool) $config->get('jobs-monitor.enabled', true)) {
            $this->app->make(Dispatcher::class)->subscribe(JobLifecycleSubscriber::class);
        }
    }
}
