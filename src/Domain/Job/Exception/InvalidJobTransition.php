<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

final class InvalidJobTransition extends DomainException
{
    public static function fromTerminalState(JobIdentifier $id, JobStatus $current): self
    {
        return new self(sprintf(
            'Job %s is already in terminal state "%s" and cannot transition further.',
            $id->value,
            $current->value,
        ));
    }
}
