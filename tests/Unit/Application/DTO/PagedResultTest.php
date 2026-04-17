<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\DTO;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\DTO\PagedResult;

final class PagedResultTest extends TestCase
{
    public function test_exposes_fields(): void
    {
        $result = new PagedResult(
            items: ['a', 'b', 'c'],
            total: 100,
            page: 2,
            perPage: 50,
        );

        self::assertSame(['a', 'b', 'c'], $result->items);
        self::assertSame(100, $result->total);
        self::assertSame(2, $result->page);
        self::assertSame(50, $result->perPage);
    }

    public function test_calculates_total_pages(): void
    {
        self::assertSame(2, (new PagedResult([], 100, 1, 50))->totalPages());
        self::assertSame(3, (new PagedResult([], 101, 1, 50))->totalPages());
        self::assertSame(1, (new PagedResult([], 0, 1, 50))->totalPages());
    }

    public function test_has_more_pages(): void
    {
        self::assertTrue((new PagedResult([], 200, 1, 50))->hasMorePages());
        self::assertFalse((new PagedResult([], 200, 4, 50))->hasMorePages());
    }

    public function test_rejects_negative_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PagedResult([], -1, 1, 50);
    }

    public function test_rejects_non_positive_page(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PagedResult([], 0, 0, 50);
    }

    public function test_rejects_non_positive_per_page(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PagedResult([], 0, 1, 0);
    }

    public function test_empty_factory_returns_empty_result_with_defaults(): void
    {
        $result = PagedResult::empty(50);

        self::assertSame([], $result->items);
        self::assertSame(0, $result->total);
        self::assertSame(1, $result->page);
        self::assertSame(50, $result->perPage);
    }
}
