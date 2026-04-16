<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Worker\ValueObject;

use Yammi\JobsMonitor\Domain\Worker\Exception\InvalidWorkerIdentifier;

final class WorkerIdentifier
{
    private const MAX_LENGTH = 191;

    public readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidWorkerIdentifier('Worker identifier must not be blank.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidWorkerIdentifier(sprintf(
                'Worker identifier must be at most %d characters, got %d.',
                self::MAX_LENGTH,
                mb_strlen($trimmed),
            ));
        }

        $this->value = $trimmed;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
