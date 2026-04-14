<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\Contract;

interface MessageNormalizationRule
{
    public function apply(string $message): string;
}
