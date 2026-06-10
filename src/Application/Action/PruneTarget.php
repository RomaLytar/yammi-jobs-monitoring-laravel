<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Closure;
use DateTimeImmutable;

/**
 * Wiring descriptor for one prunable dataset. Pairs a human-readable name and
 * its retention config path with a closure that deletes rows older than a
 * cutoff. Adding a new prunable table is a single descriptor, no new branch.
 *
 * @internal
 */
final class PruneTarget
{
    /**
     * @param  Closure(DateTimeImmutable): int  $prune  deletes rows older than the cutoff, returns the count
     * @param  bool  $overridableByDays  whether the `--days` run override applies to this dataset
     */
    public function __construct(
        public readonly string $name,
        public readonly string $retentionConfigPath,
        public readonly int $defaultDays,
        public readonly Closure $prune,
        public readonly bool $overridableByDays = true,
    ) {}
}
