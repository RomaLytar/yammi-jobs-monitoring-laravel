<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Request;

/**
 * Short-circuits every UI request when the monitor database is
 * unreachable, showing a full-page fix guide instead of a crash.
 *
 * The check is per-request so that the blocker disappears immediately
 * after the database is created (no server restart required).
 *
 * Database-settings routes are always let through so the operator
 * can reach the repair UI.
 *
 * @internal
 */
final class MonitorDbHealthMiddleware
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly ConnectionResolverInterface $db,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->routeIs('jobs-monitor.settings.database*')) {
            return $next($request);
        }

        $monitorConn  = $this->config->get('jobs-monitor.database.connection');
        $defaultConn  = (string) $this->config->get('database.default', 'default');
        $activeConn   = (string) ($monitorConn ?? $defaultConn);

        if ($monitorConn !== null && ! $this->isReachable($activeConn)) {
            return response()->view('jobs-monitor::errors.db-unreachable', [
                'jmMonitorConn' => $activeConn,
                'jmDefaultConn' => $defaultConn,
            ]);
        }

        if (! $this->isMigrated($activeConn)) {
            return response()->view('jobs-monitor::errors.db-not-migrated', [
                'jmActiveConn' => $activeConn,
            ]);
        }

        return $next($request);
    }

    private function isReachable(string $name): bool
    {
        try {
            $this->db->connection($name)->getPdo();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function isMigrated(string $name): bool
    {
        try {
            return $this->db->connection($name)->getSchemaBuilder()->hasTable('jobs_monitor');
        } catch (\Exception) {
            return false;
        }
    }
}
