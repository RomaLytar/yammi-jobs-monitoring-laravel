<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Support;

use Illuminate\Support\Str;
use Yammi\JobsMonitor\Application\Contract\UuidGenerator;

final class StrUuidGenerator implements UuidGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
