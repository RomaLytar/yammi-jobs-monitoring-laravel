<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidAttempt extends DomainException
{
    public static function lessThanOne(int $value): self
    {
        return new self(sprintf('Attempt must be at least 1, got %d.', $value));
    }
}
