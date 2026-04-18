<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard-agnostic authentication barrier for the dashboard. Runs on every
 * UI route because the dashboard exposes job metadata, exception text,
 * and DLQ payloads — all of which must be fail-closed by default.
 *
 * Resolution order:
 *   1. `jobs-monitor.ui.allow_unauthenticated` = true → open (local dev).
 *   2. `jobs-monitor.ui.guards` non-empty list → check each of those.
 *   3. Otherwise try every guard declared in `auth.guards` so that
 *      Sanctum SPAs, Passport hosts, and classic web sessions all work
 *      without host-side config. Falls back to the default guard if no
 *      list is configured.
 *
 * @internal
 */
final class RequireMonitorAuth
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly ConfigRepository $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->config->get('jobs-monitor.ui.allow_unauthenticated', false) === true) {
            return $next($request);
        }

        foreach ($this->resolveGuards() as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $next($request);
            }
        }

        // Deny rather than redirect: API-only and Sanctum SPA hosts may
        // not have a /login route at all, so a hard 403 is the sane
        // signal for an unauthenticated visitor.
        abort(403);
    }

    /**
     * @return list<string|null>
     */
    private function resolveGuards(): array
    {
        $configured = $this->config->get('jobs-monitor.ui.guards');

        if (is_array($configured) && $configured !== []) {
            $list = [];
            foreach ($configured as $guard) {
                if (is_string($guard) && $guard !== '') {
                    $list[] = $guard;
                }
            }
            if ($list !== []) {
                return $list;
            }
        }

        /** @var array<string, mixed> $guards */
        $guards = (array) $this->config->get('auth.guards', []);

        $names = array_keys($guards);

        return $names !== [] ? array_values(array_map(static fn ($n): string => (string) $n, $names)) : [null];
    }
}
