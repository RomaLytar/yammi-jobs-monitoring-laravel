<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Presentation\ViewModel\Settings;

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

    public function __construct(AlertConfigResolver $resolver)
    {
        $this->features = [
            new FeatureBlockViewModel(
                key: 'alerts',
                name: 'Alerts',
                description: 'Proactive notifications when failure thresholds are crossed.',
                enabled: $resolver->resolve()->enabled,
                manageRouteName: 'jobs-monitor.settings.alerts',
            ),
        ];
    }
}
