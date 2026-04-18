<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Scheduler\Exception;

use DomainException;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;

final class InvalidScheduledTaskTransition extends DomainException
{
    public static function fromTerminalState(string $mutex, ScheduledTaskStatus $current): self
    {
        return new self(sprintf(
            'Scheduled task run [%s] is already in terminal state [%s] and cannot transition again.',
            $mutex,
            $current->value,
        ));
    }
}
