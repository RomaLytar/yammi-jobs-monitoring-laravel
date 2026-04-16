<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Worker;

use Yammi\JobsMonitor\Application\Contract\WorkerIdentityResolver;

/**
 * Resolves the current process identity from the OS.
 *
 * `gethostname()` returns false on failure — we fall back to
 * "unknown-host" so the heartbeat write still succeeds rather than
 * crashing in an obscure way on exotic systems.
 */
final class SystemWorkerIdentityResolver implements WorkerIdentityResolver
{
    public function resolve(): array
    {
        $host = gethostname();

        return [
            'host' => $host === false ? 'unknown-host' : $host,
            'pid' => getmypid() ?: 1,
        ];
    }
}
