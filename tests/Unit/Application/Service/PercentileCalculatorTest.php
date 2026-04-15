<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Service\PercentileCalculator;

final class PercentileCalculatorTest extends TestCase
{
    public function test_empty_set_returns_zero(): void
    {
        self::assertSame(0, (new PercentileCalculator)->compute([], 50));
    }

    public function test_single_sample_returns_itself(): void
    {
        self::assertSame(42, (new PercentileCalculator)->compute([42], 95));
    }

    public function test_p50_of_even_set(): void
    {
        self::assertSame(5, (new PercentileCalculator)->compute([1, 3, 5, 7, 9], 50));
    }

    public function test_p95_of_hundred_samples(): void
    {
        $samples = range(1, 100);
        self::assertSame(95, (new PercentileCalculator)->compute($samples, 95));
    }

    public function test_is_order_independent(): void
    {
        $calc = new PercentileCalculator;
        self::assertSame($calc->compute([9, 7, 5, 3, 1], 50), $calc->compute([1, 3, 5, 7, 9], 50));
    }
}
