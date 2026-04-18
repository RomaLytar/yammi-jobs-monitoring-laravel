<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

/**
 * Read-only view of host-provided configuration. Exists so Application
 * classes do not reach into Illuminate\Contracts\Config — the adapter
 * lives in Infrastructure.
 */
interface ConfigReader
{
    public function get(string $path, mixed $default = null): mixed;
}
