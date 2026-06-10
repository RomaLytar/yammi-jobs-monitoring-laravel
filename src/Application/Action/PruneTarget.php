<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Closure;
use DateTimeImmutable;

/** @internal */
final class PruneTarget
{
    /**
     * @param  Closure(DateTimeImmutable): int  $prune
     */
    public function __construct(
        public readonly string $name,
        public readonly string $retentionConfigPath,
        public readonly int $defaultDays,
        public readonly Closure $prune,
        public readonly bool $overridableByDays = true,
    ) {}
}
