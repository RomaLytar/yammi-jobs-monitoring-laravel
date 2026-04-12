<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Contract\FailureClassifier;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Infrastructure\Classifier\PatternBasedFailureClassifier;
use Yammi\JobsMonitor\Infrastructure\Console\PruneJobRecordsCommand;
use Yammi\JobsMonitor\Infrastructure\Listener\JobLifecycleSubscriber;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentJobRecordRepository;

final class JobsMonitorServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__.'/../config/jobs-monitor.php';

    private const MIGRATIONS_PATH = __DIR__.'/../database/migrations';

    private const VIEWS_PATH = __DIR__.'/../resources/views';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'jobs-monitor');

        $this->app->bind(JobRecordRepository::class, EloquentJobRecordRepository::class);
        $this->app->bind(QueueMetricsDriver::class, NullMetricsDriver::class);
        $this->app->bind(FailureClassifier::class, function () {
            /** @var string|null $custom */
            $custom = $this->app->make(ConfigRepository::class)->get('jobs-monitor.failure_classifier');

            return $custom !== null
                ? $this->app->make($custom)
                : new PatternBasedFailureClassifier;
        });
        $this->app->singleton(JobsMonitorService::class);
        $this->app->singleton(PayloadRedactor::class);

        $this->app->when(JobLifecycleSubscriber::class)
            ->needs('$storePayload')
            ->giveConfig('jobs-monitor.store_payload', false);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(self::MIGRATIONS_PATH);
        $this->loadViewsFrom(self::VIEWS_PATH, 'jobs-monitor');

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [self::CONFIG_PATH => config_path('jobs-monitor.php')],
                'jobs-monitor-config',
            );

            $this->publishes(
                [self::MIGRATIONS_PATH => database_path('migrations')],
                'jobs-monitor-migrations',
            );

            $this->publishes(
                [self::VIEWS_PATH => resource_path('views/vendor/jobs-monitor')],
                'jobs-monitor-views',
            );

            $this->commands([
                PruneJobRecordsCommand::class,
            ]);
        }

        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        if ((bool) $config->get('jobs-monitor.enabled', true)) {
            $this->app->make(Dispatcher::class)->subscribe(JobLifecycleSubscriber::class);
        }

        $this->registerRoutes($config);
    }

    private function registerRoutes(ConfigRepository $config): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        if ((bool) $config->get('jobs-monitor.ui.enabled', true)) {
            $router->group([
                'prefix' => $config->get('jobs-monitor.ui.path', 'jobs-monitor'),
                'middleware' => $config->get('jobs-monitor.ui.middleware', ['web']),
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        if ((bool) $config->get('jobs-monitor.api.enabled', false)) {
            $router->group([
                'prefix' => $config->get('jobs-monitor.api.path', 'api/jobs-monitor'),
                'middleware' => $config->get('jobs-monitor.api.middleware', ['api']),
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }
    }
}
