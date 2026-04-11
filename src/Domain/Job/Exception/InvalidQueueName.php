<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidQueueName extends DomainException
{
    public static function blank(): self
    {
        return new self('Queue name cannot be empty or whitespace.');
    }
}
