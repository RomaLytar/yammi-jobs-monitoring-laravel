<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Checks the configured ability for /settings endpoints.
 *
 * Default: no gate (open to whatever the route middleware permits).
 * Hosts opt in via `jobs-monitor.settings.authorization = '<ability>'`.
 *
 * @internal
 */
final class SettingsGate
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ConfigRepository $config,
    ) {}

    public function authorize(): void
    {
        $ability = $this->config->get('jobs-monitor.settings.authorization');

        if (! is_string($ability) || $ability === '') {
            return;
        }

        if (! $this->gate->check($ability)) {
            abort(403);
        }
    }
}
