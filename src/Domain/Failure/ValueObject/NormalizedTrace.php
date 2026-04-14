<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\ValueObject;

use Yammi\JobsMonitor\Domain\Failure\Exception\InvalidNormalizedTrace;

final class NormalizedTrace
{
    public function __construct(
        public readonly string $exceptionClass,
        public readonly string $normalizedMessage,
        public readonly string $firstUserFrame,
    ) {
        if ($exceptionClass === '') {
            throw new InvalidNormalizedTrace('exceptionClass must not be empty.');
        }

        if ($firstUserFrame === '') {
            throw new InvalidNormalizedTrace('firstUserFrame must not be empty; pass an explicit sentinel for unknown frames.');
        }
    }

    public function signature(): string
    {
        return $this->exceptionClass.'|'.$this->firstUserFrame.'|'.$this->normalizedMessage;
    }

    public function equals(self $other): bool
    {
        return $this->exceptionClass === $other->exceptionClass
            && $this->normalizedMessage === $other->normalizedMessage
            && $this->firstUserFrame === $other->firstUserFrame;
    }
}
