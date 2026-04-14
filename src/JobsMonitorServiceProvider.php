<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Yammi\JobsMonitor\Application\Action\EvaluateAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\GetAlertSettingsAction;
use Yammi\JobsMonitor\Application\Action\ResetBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\SendAlertAction;
use Yammi\JobsMonitor\Application\Action\ToggleBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;
use Yammi\JobsMonitor\Application\Service\AlertRuleEvaluator;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Alert\Contract\AlertThrottle;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Job\Contract\FailureClassifier;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\MailNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\SlackNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Job\DispatchAlertsJob;
use Yammi\JobsMonitor\Infrastructure\Alert\Throttle\CacheAlertThrottle;
use Yammi\JobsMonitor\Infrastructure\Classifier\PatternBasedFailureClassifier;
use Yammi\JobsMonitor\Infrastructure\Console\PruneJobRecordsCommand;
use Yammi\JobsMonitor\Infrastructure\Listener\JobLifecycleSubscriber;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentFailureGroupRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentJobRecordRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentAlertSettingsRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentManagedAlertRuleRepository;

final class JobsMonitorServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__.'/../config/jobs-monitor.php';

    private const MIGRATIONS_PATH = __DIR__.'/../database/migrations';

    private const VIEWS_PATH = __DIR__.'/../resources/views';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'jobs-monitor');

        $this->app->bind(JobRecordRepository::class, EloquentJobRecordRepository::class);
        $this->app->bind(FailureGroupRepository::class, EloquentFailureGroupRepository::class);
        $this->app->bind(AlertSettingsRepository::class, EloquentAlertSettingsRepository::class);
        $this->app->bind(ManagedAlertRuleRepository::class, EloquentManagedAlertRuleRepository::class);
        $this->app->bind(BuiltInRuleStateRepository::class, EloquentBuiltInRuleStateRepository::class);
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

        $this->registerAlertBindings();
        $this->registerSettingsBindings();
    }

    private function registerSettingsBindings(): void
    {
        $this->app->bind(GetAlertSettingsAction::class, function () {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);

            $rawConfigEnabled = $config->get('jobs-monitor.alerts.enabled');
            $configEnabled = is_bool($rawConfigEnabled) ? $rawConfigEnabled : null;

            /** @var list<string> $configRecipients */
            $configRecipients = array_values(array_filter(
                (array) $config->get('jobs-monitor.alerts.channels.mail.to', []),
                static fn ($email): bool => is_string($email) && $email !== '',
            ));

            return new GetAlertSettingsAction(
                repo: $this->app->make(AlertSettingsRepository::class),
                configEnabled: $configEnabled,
                configSourceName: $this->explicitConfigSourceName($config),
                autoSourceName: $this->autoSourceName($config),
                configMonitorUrl: $this->explicitConfigMonitorUrl($config),
                autoMonitorUrl: $this->autoMonitorUrl($config),
                configRecipients: $configRecipients,
            );
        });

        $this->app->bind(ToggleBuiltInRuleAction::class, function () {
            return new ToggleBuiltInRuleAction(
                $this->app->make(BuiltInRuleStateRepository::class),
                $this->app->make(ManagedAlertRuleRepository::class),
            );
        });

        $this->app->bind(ResetBuiltInRuleAction::class, function () {
            return new ResetBuiltInRuleAction(
                $this->app->make(ManagedAlertRuleRepository::class),
                $this->app->make(BuiltInRuleStateRepository::class),
            );
        });
    }

    private function registerAlertBindings(): void
    {
        $this->app->singleton(AlertRuleFactory::class);
        $this->app->singleton(BuiltInRulesProvider::class);

        $this->app->bind(AlertThrottle::class, function () {
            return new CacheAlertThrottle(
                $this->app->make(CacheFactory::class)->store(),
            );
        });

        $this->app->bind(SendAlertAction::class, function () {
            return new SendAlertAction(
                $this->resolveAlertChannels(),
                $this->app->make(LoggerInterface::class),
            );
        });

        $this->app->bind(AlertConfigResolver::class, function () {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);

            return new AlertConfigResolver(
                settingsRepo: $this->app->make(AlertSettingsRepository::class),
                rulesRepo: $this->app->make(ManagedAlertRuleRepository::class),
                builtInStateRepo: $this->app->make(BuiltInRuleStateRepository::class),
                builtInRulesProvider: $this->app->make(BuiltInRulesProvider::class),
                ruleFactory: $this->app->make(AlertRuleFactory::class),
                configEnabled: (bool) $config->get('jobs-monitor.alerts.enabled', false),
                builtInConfigOverrides: (array) $config->get('jobs-monitor.alerts.built_in', []),
                configCustomRules: (array) $config->get('jobs-monitor.alerts.custom_rules', []),
            );
        });

        $this->app->bind(EvaluateAlertRulesAction::class, function () {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);

            return new EvaluateAlertRulesAction(
                new AlertRuleEvaluator(
                    $this->app->make(JobRecordRepository::class),
                    (int) $config->get('jobs-monitor.max_tries', 3),
                ),
                $this->app->make(SendAlertAction::class),
                $this->app->make(AlertThrottle::class),
                $this->app->make(LoggerInterface::class),
                $this->app->make(AlertConfigResolver::class),
            );
        });
    }

    /**
     * @return list<NotificationChannel>
     */
    private function resolveAlertChannels(): array
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        $sourceName = $this->resolveSourceName($config);
        $monitorUrl = $this->resolveMonitorUrl($config);

        $channels = [];

        $slackUrl = $config->get('jobs-monitor.alerts.channels.slack.webhook_url');
        if (is_string($slackUrl) && $slackUrl !== '') {
            $channels[] = new SlackNotificationChannel(
                $this->app->make(HttpFactory::class),
                $slackUrl,
                $config->get('jobs-monitor.alerts.channels.slack.signing_secret'),
                $sourceName,
                $monitorUrl,
            );
        }

        /** @var list<string> $mailTo */
        $mailTo = (array) $config->get('jobs-monitor.alerts.channels.mail.to', []);
        if ($mailTo !== []) {
            $channels[] = new MailNotificationChannel(
                $this->app->make(Mailer::class),
                $mailTo,
                $sourceName,
                $monitorUrl,
            );
        }

        return $channels;
    }

    private function resolveSourceName(ConfigRepository $config): ?string
    {
        return $this->explicitConfigSourceName($config) ?? $this->autoSourceName($config);
    }

    private function resolveMonitorUrl(ConfigRepository $config): ?string
    {
        return $this->explicitConfigMonitorUrl($config) ?? $this->autoMonitorUrl($config);
    }

    private function explicitConfigSourceName(ConfigRepository $config): ?string
    {
        $value = $config->get('jobs-monitor.alerts.source_name');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function autoSourceName(ConfigRepository $config): ?string
    {
        $appName = $config->get('app.name');
        if (! is_string($appName) || $appName === '') {
            return null;
        }

        $env = $config->get('app.env');

        return is_string($env) && $env !== '' && $env !== 'production'
            ? sprintf('%s (%s)', $appName, $env)
            : $appName;
    }

    private function explicitConfigMonitorUrl(ConfigRepository $config): ?string
    {
        $value = $config->get('jobs-monitor.alerts.monitor_url');

        return is_string($value) && $value !== '' ? rtrim($value, '/') : null;
    }

    private function autoMonitorUrl(ConfigRepository $config): ?string
    {
        $appUrl = $config->get('app.url');
        if (! is_string($appUrl) || $appUrl === '') {
            return null;
        }

        $uiPath = (string) $config->get('jobs-monitor.ui.path', 'jobs-monitor');

        return rtrim($appUrl, '/').'/'.trim($uiPath, '/');
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
        $this->registerAlertSchedule($config);
    }

    private function registerAlertSchedule(ConfigRepository $config): void
    {
        // The schedule no longer gates on alerts.enabled — that toggle now
        // lives in the DB and is resolved per evaluation tick. The action
        // short-circuits when the resolver reports the feature off, so the
        // scheduler is safe to register unconditionally. Hosts that want to
        // fully kill the cron entry use alerts.schedule.enabled = false.
        if (! (bool) $config->get('jobs-monitor.alerts.schedule.enabled', true)) {
            return;
        }

        $this->app->booted(function (): void {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $event = $schedule
                ->job(DispatchAlertsJob::class, (string) $config->get('jobs-monitor.alerts.schedule.queue', ''))
                ->cron((string) $config->get('jobs-monitor.alerts.schedule.cron', '* * * * *'))
                ->name('jobs-monitor:dispatch-alerts')
                ->withoutOverlapping();
        });
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
