<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\ValueObject;

use DateTimeInterface;
use Yammi\JobsMonitor\Domain\Job\Exception\InvalidDuration;

final class Duration
{
    public function __construct(public readonly int $milliseconds)
    {
        if ($milliseconds < 0) {
            throw InvalidDuration::negative($milliseconds);
        }
    }

    public static function fromMilliseconds(int $milliseconds): self
    {
        return new self($milliseconds);
    }

    public static function between(DateTimeInterface $start, DateTimeInterface $end): self
    {
        $startMs = ((int) $start->format('U')) * 1000 + intdiv((int) $start->format('u'), 1000);
        $endMs = ((int) $end->format('U')) * 1000 + intdiv((int) $end->format('u'), 1000);

        return new self(max(0, $endMs - $startMs));
    }

    public function equals(self $other): bool
    {
        return $this->milliseconds === $other->milliseconds;
    }
}
