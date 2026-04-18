<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

final class JobProgress
{
    public function __construct(
        public readonly int $current,
        public readonly ?int $total,
        public readonly ?string $description,
        public readonly DateTimeImmutable $updatedAt,
    ) {
        if ($current < 0) {
            throw new InvalidArgumentException('Progress current must be non-negative.');
        }
        if ($total !== null && $total < $current) {
            throw new InvalidArgumentException('Progress total must be >= current.');
        }
    }

    public function isComplete(): bool
    {
        return $this->total !== null && $this->current === $this->total;
    }

    public function isPartial(): bool
    {
        return $this->current > 0 && ! $this->isComplete();
    }

    public function percentage(): ?float
    {
        if ($this->total === null || $this->total === 0) {
            return null;
        }

        return round(($this->current / $this->total) * 100, 2);
    }
}
