<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Contract;

/**
 * Emits RFC 4122 UUIDs. Exists so Application classes can produce
 * identifiers without importing Illuminate\Support\Str.
 */
interface UuidGenerator
{
    public function generate(): string;
}
