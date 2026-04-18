<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
<<<<<<< HEAD
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Fail-closed authorization guard for /settings endpoints.
 *
 * Resolution order:
 *   1. `jobs-monitor.settings.allow_unauthenticated` = true → open (local dev only).
 *   2. `jobs-monitor.settings.authorization` is a non-empty string → Gate::check.
 *   3. Otherwise require an authenticated user on the configured guard.
 *
 * Any other outcome aborts with 403. This prevents the previous behaviour
 * where a missing ability opened the panel — including destructive
 * transfer/migrate/playground actions — to unauthenticated traffic.
=======
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Checks the configured ability for /settings endpoints.
 *
 * Default: no gate (open to whatever the route middleware permits).
 * Hosts opt in via `jobs-monitor.settings.authorization = '<ability>'`.
>>>>>>> origin/main
 *
 * @internal
 */
final class SettingsGate
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ConfigRepository $config,
<<<<<<< HEAD
        private readonly AuthFactory $auth,
=======
>>>>>>> origin/main
    ) {}

    public function authorize(): void
    {
        $ability = $this->config->get('jobs-monitor.settings.authorization');

<<<<<<< HEAD
        if (is_string($ability) && $ability !== '') {
            if (! $this->gate->check($ability)) {
                abort(403);
            }

            return;
        }

        if ($this->config->get('jobs-monitor.settings.allow_unauthenticated', false) === true) {
            return;
        }

        $guard = $this->config->get('jobs-monitor.settings.guard');
        $guardName = is_string($guard) && $guard !== '' ? $guard : null;

        if (! $this->auth->guard($guardName)->check()) {
=======
        if (! is_string($ability) || $ability === '') {
            return;
        }

        if (! $this->gate->check($ability)) {
>>>>>>> origin/main
            abort(403);
        }
    }
}
