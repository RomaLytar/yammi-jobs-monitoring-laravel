<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidJobIdentifier extends DomainException
{
    public static function malformed(string $value): self
    {
        return new self(sprintf('Job identifier is not a valid UUID: "%s".', $value));
    }
}
