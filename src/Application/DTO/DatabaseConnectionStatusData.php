<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

final class DatabaseConnectionStatusData
{
    public function __construct(
        public readonly string $name,
        public readonly string $driver,
        public readonly string $database,
        public readonly bool $reachable,
        public readonly bool $migrated,
        public readonly int $rowCount,
    ) {}
}
