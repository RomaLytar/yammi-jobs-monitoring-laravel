<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\ValueObject;

use Yammi\JobsMonitor\Domain\Job\Exception\InvalidAttempt;

final class Attempt
{
    public function __construct(public readonly int $value)
    {
        if ($value < 1) {
            throw InvalidAttempt::lessThanOne($value);
        }
    }

    public static function first(): self
    {
        return new self(1);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
