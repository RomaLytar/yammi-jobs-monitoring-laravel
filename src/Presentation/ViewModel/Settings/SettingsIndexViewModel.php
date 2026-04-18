<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel\Settings;

<<<<<<< HEAD
use Illuminate\Contracts\Config\Repository as ConfigRepository;
=======
>>>>>>> origin/main
use Yammi\JobsMonitor\Application\Service\AlertConfigResolver;

/**
 * Aggregates feature blocks displayed on the /settings index page.
 *
 * @internal
 */
final class SettingsIndexViewModel
{
    /** @var list<FeatureBlockViewModel> */
    public readonly array $features;

<<<<<<< HEAD
    public function __construct(AlertConfigResolver $resolver, ConfigRepository $config)
    {
        $this->features = [
            new FeatureBlockViewModel(
                key: 'general',
                name: 'General Settings',
                description: 'Feature toggles, retention, bulk limits, scheduler, anomaly detection, workers.',
                enabled: true,
                manageRouteName: 'jobs-monitor.settings.general',
            ),
            new FeatureBlockViewModel(
=======
    public function __construct(AlertConfigResolver $resolver)
    {
        $this->features = [
            new FeatureBlockViewModel(
>>>>>>> origin/main
                key: 'alerts',
                name: 'Alerts',
                description: 'Proactive notifications when failure thresholds are crossed.',
                enabled: $resolver->resolve()->enabled,
                manageRouteName: 'jobs-monitor.settings.alerts',
            ),
<<<<<<< HEAD
            new FeatureBlockViewModel(
                key: 'database',
                name: 'Database Connection',
                description: 'Transfer monitoring data between connections. Isolate observability data from your app database.',
                enabled: $config->get('jobs-monitor.database.connection') !== null,
                manageRouteName: 'jobs-monitor.settings.database',
            ),
            new FeatureBlockViewModel(
                key: 'playground',
                name: 'Facade Playground',
                description: 'Try every public facade method from the UI. Browse the catalog, run a call, see the JSON result.',
                enabled: true,
                manageRouteName: 'jobs-monitor.settings.playground',
            ),
=======
>>>>>>> origin/main
        ];
    }
}
