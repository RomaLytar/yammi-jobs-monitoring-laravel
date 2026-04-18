<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

use InvalidArgumentException;

/**
 * @template T
 */
final class PagedResult
{
    /**
     * @param  array<int, T>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException('PagedResult total cannot be negative.');
        }

        if ($page < 1) {
            throw new InvalidArgumentException('PagedResult page must be >= 1.');
        }

        if ($perPage < 1) {
            throw new InvalidArgumentException('PagedResult perPage must be >= 1.');
        }
    }

    /**
     * @return self<never>
     */
    public static function empty(int $perPage): self
    {
        return new self([], 0, 1, $perPage);
    }

    public function totalPages(): int
    {
        if ($this->total === 0) {
            return 1;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function hasMorePages(): bool
    {
        return $this->page < $this->totalPages();
    }
}
