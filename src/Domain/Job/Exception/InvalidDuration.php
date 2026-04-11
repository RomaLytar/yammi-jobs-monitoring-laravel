<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidDuration extends DomainException
{
    public static function negative(int $milliseconds): self
    {
        return new self(sprintf('Duration cannot be negative, got %d ms.', $milliseconds));
    }
}
