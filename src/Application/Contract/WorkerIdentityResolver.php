<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

/**
 * Resolves the identity of the running queue worker process.
 *
 * Needed because Laravel's `Looping` / `WorkerStopping` events do not
 * carry host/pid information. Infrastructure reads these from the OS;
 * tests substitute a fixed-value fake.
 *
 * @phpstan-type ResolvedIdentity array{host: string, pid: int}
 */
interface WorkerIdentityResolver
{
    /**
     * @return array{host: string, pid: int}
     */
    public function resolve(): array;
}
