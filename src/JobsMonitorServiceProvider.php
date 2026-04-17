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
use Yammi\JobsMonitor\Application\Action\DetectDurationAnomalyAction;
use Yammi\JobsMonitor\Application\Action\DetectSilentWorkersAction;
use Yammi\JobsMonitor\Application\Action\EvaluateAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\GetAlertSettingsAction;
use Yammi\JobsMonitor\Application\Action\RecordWorkerHeartbeatAction;
use Yammi\JobsMonitor\Application\Action\ResetBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\SendAlertAction;
use Yammi\JobsMonitor\Application\Action\ToggleBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Contract\HeartbeatRateLimiter;
use Yammi\JobsMonitor\Application\Contract\QueueMetricsDriver;
use Yammi\JobsMonitor\Application\Contract\WorkerAlertStateStore;
use Yammi\JobsMonitor\Application\Contract\WorkerIdentityResolver;
use Yammi\JobsMonitor\Application\DTO\ChannelStatusData;
use Yammi\JobsMonitor\Application\Playground\ArgumentCoercer;
use Yammi\JobsMonitor\Application\Playground\MethodCatalog;
use Yammi\JobsMonitor\Application\Playground\ResultSerializer;
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;
use Yammi\JobsMonitor\Application\Service\AlertRuleEvaluator;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Application\Service\JobsMonitorService;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Application\Service\PercentileCalculator;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Application\Service\YammiJobsManageService;
use Yammi\JobsMonitor\Application\Service\YammiJobsQueryService;
use Yammi\JobsMonitor\Application\Service\YammiJobsSettingsService;
use Yammi\JobsMonitor\Domain\Alert\Contract\AlertThrottle;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Failure\Contract\TraceNormalizer;
use Yammi\JobsMonitor\Domain\Failure\Contract\TraceRedactor;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Job\Contract\FailureClassifier;
use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\AlertSettingsRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\BuiltInRuleStateRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\GeneralSettingRepository;
use Yammi\JobsMonitor\Domain\Settings\Repository\ManagedAlertRuleRepository;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\MailNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\OpsgenieNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\PagerDutyNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\SlackNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Channel\WebhookNotificationChannel;
use Yammi\JobsMonitor\Infrastructure\Alert\Job\DispatchAlertsJob;
use Yammi\JobsMonitor\Infrastructure\Alert\Throttle\CacheAlertThrottle;
use Yammi\JobsMonitor\Infrastructure\Classifier\PatternBasedFailureClassifier;
use Yammi\JobsMonitor\Infrastructure\Console\Command\CheckWorkerHeartbeatsCommand;
use Yammi\JobsMonitor\Infrastructure\Console\Command\DetectLateScheduledTasksCommand;
use Yammi\JobsMonitor\Infrastructure\Console\Command\RefreshDurationBaselinesCommand;
use Yammi\JobsMonitor\Infrastructure\Console\Command\TransferDataCommand;
use Yammi\JobsMonitor\Infrastructure\Console\PruneJobRecordsCommand;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeEmailInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeNumbersInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeTimestampInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeUuidInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Service\DefaultTraceRedactor;
use Yammi\JobsMonitor\Infrastructure\Failure\Service\RuleBasedTraceNormalizer;
use Yammi\JobsMonitor\Infrastructure\Http\Middleware\MonitorDbHealthMiddleware;
use Yammi\JobsMonitor\Infrastructure\Listener\DurationAnomalySubscriber;
use Yammi\JobsMonitor\Infrastructure\Listener\JobLifecycleSubscriber;
use Yammi\JobsMonitor\Infrastructure\Listener\OutcomeReportSubscriber;
use Yammi\JobsMonitor\Infrastructure\Listener\SchedulerSubscriber;
use Yammi\JobsMonitor\Infrastructure\Listener\WorkerHeartbeatSubscriber;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentDurationBaselineRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentFailureGroupRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentJobRecordRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentScheduledTaskRunRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Repository\EloquentWorkerRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentAlertSettingsRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentGeneralSettingRepository;
use Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Repository\EloquentManagedAlertRuleRepository;
use Yammi\JobsMonitor\Infrastructure\Worker\CacheHeartbeatRateLimiter;
use Yammi\JobsMonitor\Infrastructure\Worker\CacheWorkerAlertStateStore;
use Yammi\JobsMonitor\Infrastructure\Worker\SystemWorkerIdentityResolver;

final class JobsMonitorServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__.'/../config/jobs-monitor.php';

    private const CONFIG_DEFAULTS_PATH = __DIR__.'/../config/jobs-monitor-defaults.php';

    private const MIGRATIONS_PATH = __DIR__.'/../database/migrations';

    private const VIEWS_PATH = __DIR__.'/../resources/views';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'jobs-monitor');

        // Deep-merge operational defaults so nested keys (e.g. alerts.enabled)
        // fill in without stomping credentials already set in the critical file.
        $defaults = require self::CONFIG_DEFAULTS_PATH;
        $appConfig = $this->app->make(ConfigRepository::class);
        $current = $appConfig->get('jobs-monitor', []);
        $appConfig->set('jobs-monitor', array_replace_recursive($defaults, $current));

        $this->app->bind(JobRecordRepository::class, EloquentJobRecordRepository::class);
        $this->app->bind(FailureGroupRepository::class, EloquentFailureGroupRepository::class);
        $this->app->bind(ScheduledTaskRunRepository::class, EloquentScheduledTaskRunRepository::class);
        $this->app->bind(DurationBaselineRepository::class, EloquentDurationBaselineRepository::class);
        $this->app->bind(WorkerRepository::class, EloquentWorkerRepository::class);
        $this->app->bind(WorkerIdentityResolver::class, SystemWorkerIdentityResolver::class);
        $this->app->bind(HeartbeatRateLimiter::class, function () {
            return new CacheHeartbeatRateLimiter(
                $this->app->make(CacheFactory::class)->store(),
            );
        });
        $this->app->bind(RecordWorkerHeartbeatAction::class, function () {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);

            return new RecordWorkerHeartbeatAction(
                repository: $this->app->make(WorkerRepository::class),
                rateLimiter: $this->app->make(HeartbeatRateLimiter::class),
                intervalSeconds: (int) $config->get('jobs-monitor.workers.heartbeat_interval_seconds', 30),
            );
        });
        $this->app->bind(WorkerAlertStateStore::class, function () {
            return new CacheWorkerAlertStateStore(
                $this->app->make(CacheFactory::class)->store(),
            );
        });
        $this->app->bind(DetectSilentWorkersAction::class, function () {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);

            return new DetectSilentWorkersAction(
                repository: $this->app->make(WorkerRepository::class),
                stateStore: $this->app->make(WorkerAlertStateStore::class),
                sender: $this->app->make(SendAlertAction::class),
                silentAfterSeconds: (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120),
                expected: $this->workerExpectations($config),
                channels: $this->workerChannels($config),
            );
        });
        $this->app->singleton(PercentileCalculator::class);
        $this->app->bind(DetectDurationAnomalyAction::class, function () {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);

            return new DetectDurationAnomalyAction(
                repository: $this->app->make(DurationBaselineRepository::class),
                minSamples: (int) $config->get('jobs-monitor.duration_anomaly.min_samples', 30),
                shortFactor: (float) $config->get('jobs-monitor.duration_anomaly.short_factor', 0.1),
                longFactor: (float) $config->get('jobs-monitor.duration_anomaly.long_factor', 3.0),
            );
        });
        $this->app->bind(TraceNormalizer::class, function () {
            return new RuleBasedTraceNormalizer(rules: [
                new NormalizeUuidInMessageRule,
                new NormalizeEmailInMessageRule,
                new NormalizeTimestampInMessageRule,
                new NormalizeNumbersInMessageRule,
            ]);
        });
        $this->app->singleton(TraceRedactor::class, DefaultTraceRedactor::class);
        $this->app->bind(AlertSettingsRepository::class, EloquentAlertSettingsRepository::class);
        $this->app->bind(ManagedAlertRuleRepository::class, EloquentManagedAlertRuleRepository::class);
        $this->app->bind(BuiltInRuleStateRepository::class, EloquentBuiltInRuleStateRepository::class);
        $this->app->bind(GeneralSettingRepository::class, EloquentGeneralSettingRepository::class);
        $this->app->singleton(SettingRegistry::class);
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

        $this->app->singleton(YammiJobsQueryService::class);
        $this->app->singleton(YammiJobsManageService::class);
        $this->app->singleton(YammiJobsSettingsService::class);

        $this->app->singleton(MethodCatalog::class);
        $this->app->singleton(ArgumentCoercer::class);
        $this->app->singleton(ResultSerializer::class);

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
                channels: $this->resolveChannelStatuses($config),
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
                    $this->app->make(FailureGroupRepository::class),
                    (int) $config->get('jobs-monitor.max_tries', 3),
                    $this->app->make(ScheduledTaskRunRepository::class),
                    $this->app->make(DurationBaselineRepository::class),
                ),
                $this->app->make(SendAlertAction::class),
                $this->app->make(AlertThrottle::class),
                $this->app->make(LoggerInterface::class),
                $this->app->make(AlertConfigResolver::class),
            );
        });
    }

    /**
     * Builds a presentation-layer snapshot of each channel's config
     * status. The list is the single source used by both the Blade
     * _channels partial and the API response, so adding a transport
     * is one new entry here instead of two parallel lists.
     *
     * @return list<ChannelStatusData>
     */
    private function resolveChannelStatuses(ConfigRepository $config): array
    {
        $catalog = [
            [
                'name' => 'slack',
                'label' => 'Slack',
                'icon' => 'slack',
                'purpose' => 'ChatOps — team discussion channel.',
                'envVar' => 'JOBS_MONITOR_SLACK_WEBHOOK',
                'configuredKey' => 'jobs-monitor.alerts.channels.slack.webhook_url',
            ],
            [
                'name' => 'mail',
                'label' => 'Mail',
                'icon' => 'mail',
                'purpose' => 'Per-recipient email.',
                'envVar' => 'JOBS_MONITOR_ALERT_MAIL_TO',
                'configuredKey' => 'jobs-monitor.alerts.channels.mail.to',
            ],
            [
                'name' => 'pagerduty',
                'label' => 'PagerDuty',
                'icon' => 'siren',
                'purpose' => 'Incident management — phones/SMS on-call rotation.',
                'envVar' => 'JOBS_MONITOR_PAGERDUTY_ROUTING_KEY',
                'configuredKey' => 'jobs-monitor.alerts.channels.pagerduty.routing_key',
            ],
            [
                'name' => 'opsgenie',
                'label' => 'Opsgenie',
                'icon' => 'shield-alert',
                'purpose' => 'Incident management (Atlassian stack).',
                'envVar' => 'JOBS_MONITOR_OPSGENIE_API_KEY',
                'configuredKey' => 'jobs-monitor.alerts.channels.opsgenie.api_key',
            ],
            [
                'name' => 'webhook',
                'label' => 'Webhook',
                'icon' => 'webhook',
                'purpose' => 'Generic signed JSON POST (Grafana OnCall, internal hubs).',
                'envVar' => 'JOBS_MONITOR_WEBHOOK_URL',
                'configuredKey' => 'jobs-monitor.alerts.channels.webhook.url',
            ],
        ];

        $statuses = [];
        foreach ($catalog as $entry) {
            $raw = $config->get($entry['configuredKey']);
            $configured = match (true) {
                is_string($raw) => $raw !== '',
                is_array($raw) => array_values(array_filter($raw, static fn ($v): bool => is_string($v) && $v !== '')) !== [],
                default => false,
            };

            $statuses[] = new ChannelStatusData(
                name: $entry['name'],
                label: $entry['label'],
                icon: $entry['icon'],
                purpose: $entry['purpose'],
                configured: $configured,
                envVar: $entry['envVar'],
            );
        }

        return $statuses;
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

        return array_values(array_filter([
            $this->resolveSlackChannel($config, $sourceName, $monitorUrl),
            $this->resolveMailChannel($config, $sourceName, $monitorUrl),
            $this->resolvePagerDutyChannel($config, $sourceName, $monitorUrl),
            $this->resolveOpsgenieChannel($config, $sourceName, $monitorUrl),
            $this->resolveWebhookChannel($config, $sourceName, $monitorUrl),
        ]));
    }

    private function resolveSlackChannel(ConfigRepository $config, ?string $sourceName, ?string $monitorUrl): ?SlackNotificationChannel
    {
        $url = $config->get('jobs-monitor.alerts.channels.slack.webhook_url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        return new SlackNotificationChannel(
            $this->app->make(HttpFactory::class),
            $url,
            $config->get('jobs-monitor.alerts.channels.slack.signing_secret'),
            $sourceName,
            $monitorUrl,
        );
    }

    private function resolveMailChannel(ConfigRepository $config, ?string $sourceName, ?string $monitorUrl): ?MailNotificationChannel
    {
        /** @var list<string> $mailTo */
        $mailTo = (array) $config->get('jobs-monitor.alerts.channels.mail.to', []);

        if ($mailTo === []) {
            return null;
        }

        return new MailNotificationChannel(
            $this->app->make(Mailer::class),
            $mailTo,
            $sourceName,
            $monitorUrl,
        );
    }

    private function resolvePagerDutyChannel(ConfigRepository $config, ?string $sourceName, ?string $monitorUrl): ?PagerDutyNotificationChannel
    {
        $key = $config->get('jobs-monitor.alerts.channels.pagerduty.routing_key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        return new PagerDutyNotificationChannel(
            $this->app->make(HttpFactory::class),
            $this->app->make(LoggerInterface::class),
            $key,
            $sourceName,
            $monitorUrl,
        );
    }

    private function resolveOpsgenieChannel(ConfigRepository $config, ?string $sourceName, ?string $monitorUrl): ?OpsgenieNotificationChannel
    {
        $key = $config->get('jobs-monitor.alerts.channels.opsgenie.api_key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        $region = $config->get('jobs-monitor.alerts.channels.opsgenie.region', 'us');

        return new OpsgenieNotificationChannel(
            $this->app->make(HttpFactory::class),
            $this->app->make(LoggerInterface::class),
            $key,
            is_string($region) && $region !== '' ? $region : 'us',
            $sourceName,
            $monitorUrl,
        );
    }

    private function resolveWebhookChannel(ConfigRepository $config, ?string $sourceName, ?string $monitorUrl): ?WebhookNotificationChannel
    {
        $url = $config->get('jobs-monitor.alerts.channels.webhook.url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $secret = $config->get('jobs-monitor.alerts.channels.webhook.secret');
        /** @var array<string, string> $extraHeaders */
        $extraHeaders = (array) $config->get('jobs-monitor.alerts.channels.webhook.headers', []);
        $timeout = (int) $config->get('jobs-monitor.alerts.channels.webhook.timeout', 5);

        return new WebhookNotificationChannel(
            $this->app->make(HttpFactory::class),
            $this->app->make(LoggerInterface::class),
            $url,
            is_string($secret) && $secret !== '' ? $secret : null,
            $extraHeaders,
            $timeout > 0 ? $timeout : 5,
            $sourceName,
            $monitorUrl,
        );
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

        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        $this->checkMonitorDbHealth($config);

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
                DetectLateScheduledTasksCommand::class,
                RefreshDurationBaselinesCommand::class,
                CheckWorkerHeartbeatsCommand::class,
            ]);
        }

        // Registered outside runningInConsole() so it can be called
        // programmatically from web requests (e.g. DatabaseSettingsController).
        $this->commands([TransferDataCommand::class]);

        if ((bool) $config->get('jobs-monitor.enabled', true)) {
            /** @var Dispatcher $dispatcher */
            $dispatcher = $this->app->make(Dispatcher::class);

            $dispatcher->subscribe(JobLifecycleSubscriber::class);

            if ((bool) $config->get('jobs-monitor.scheduler.enabled', true)) {
                // Must be singleton so $startedAtByMutex persists across Starting→Failed/Finished events.
                $this->app->singleton(SchedulerSubscriber::class);
                $dispatcher->subscribe(SchedulerSubscriber::class);
            }

            if ((bool) $config->get('jobs-monitor.duration_anomaly.enabled', true)) {
                $dispatcher->subscribe(DurationAnomalySubscriber::class);
            }

            if ((bool) $config->get('jobs-monitor.outcome.enabled', true)) {
                $dispatcher->subscribe(OutcomeReportSubscriber::class);
            }

            if ((bool) $config->get('jobs-monitor.workers.enabled', true)) {
                $dispatcher->subscribe(WorkerHeartbeatSubscriber::class);
            }
        }

        $this->registerRoutes($config);
        $this->registerAlertSchedule($config);
        $this->registerSchedulerWatchdog($config);
        $this->registerWorkerWatchdog($config);
    }

    private function registerWorkerWatchdog(ConfigRepository $config): void
    {
        if (! (bool) $config->get('jobs-monitor.workers.enabled', true)) {
            return;
        }

        if (! (bool) $config->get('jobs-monitor.workers.schedule.enabled', true)) {
            return;
        }

        $this->app->booted(function (): void {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $cron = (string) $config->get('jobs-monitor.workers.schedule.cron', '* * * * *');

            $schedule->command(CheckWorkerHeartbeatsCommand::class)
                ->cron($cron)
                ->name('jobs-monitor:heartbeats-check')
                ->withoutOverlapping();
        });
    }

    /**
     * @return array<string, int>
     */
    private function workerExpectations(ConfigRepository $config): array
    {
        /** @var array<mixed, mixed> $raw */
        $raw = (array) $config->get('jobs-monitor.workers.expected', []);

        $expectations = [];
        foreach ($raw as $queueKey => $min) {
            if (! is_string($queueKey) || $queueKey === '') {
                continue;
            }

            $minInt = is_int($min) ? $min : (int) $min;
            if ($minInt <= 0) {
                continue;
            }

            $expectations[$queueKey] = $minInt;
        }

        return $expectations;
    }

    /**
     * @return list<string>
     */
    private function workerChannels(ConfigRepository $config): array
    {
        /** @var array<mixed, mixed> $raw */
        $raw = (array) $config->get('jobs-monitor.workers.channels', [
            'slack', 'mail', 'pagerduty', 'opsgenie', 'webhook',
        ]);

        return array_values(array_filter(
            array_map(static fn ($v): string => is_string($v) ? $v : '', $raw),
            static fn (string $v): bool => $v !== '',
        ));
    }

    private function registerSchedulerWatchdog(ConfigRepository $config): void
    {
        if (! (bool) $config->get('jobs-monitor.scheduler.enabled', true)) {
            return;
        }

        if (! (bool) $config->get('jobs-monitor.scheduler.watchdog.enabled', true)) {
            return;
        }

        $this->app->booted(function (): void {
            /** @var ConfigRepository $config */
            $config = $this->app->make(ConfigRepository::class);
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $tolerance = (int) $config->get('jobs-monitor.scheduler.watchdog.tolerance_minutes', 30);
            $cron = (string) $config->get('jobs-monitor.scheduler.watchdog.cron', '*/5 * * * *');

            $schedule->command(DetectLateScheduledTasksCommand::class, ['--tolerance' => $tolerance])
                ->cron($cron)
                ->name('jobs-monitor:scheduled-scan')
                ->withoutOverlapping();
        });
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
            $uiMiddleware = array_merge(
                (array) $config->get('jobs-monitor.ui.middleware', ['web']),
                [MonitorDbHealthMiddleware::class],
            );

            $router->group([
                'prefix' => $config->get('jobs-monitor.ui.path', 'jobs-monitor'),
                'middleware' => $uiMiddleware,
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

    private function checkMonitorDbHealth(ConfigRepository $config): void
    {
        $monitorConn = $config->get('jobs-monitor.database.connection');

        if ($monitorConn === null) {
            return;
        }

        try {
            $this->app->make(\Illuminate\Database\ConnectionResolverInterface::class)
                ->connection((string) $monitorConn)
                ->getPdo();
        } catch (\Exception) {
            $this->app->make(LoggerInterface::class)->warning(
                sprintf(
                    'jobs-monitor: monitor connection "%s" is unreachable; monitoring is disabled until the database is available.',
                    $monitorConn,
                ),
            );

            $this->app->instance('jobs-monitor.db_unreachable', true);
            $config->set('jobs-monitor.enabled', false);
        }
    }
}
