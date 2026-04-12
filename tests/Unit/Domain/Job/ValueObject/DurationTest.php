<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Exception\InvalidDuration;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Duration;

final class DurationTest extends TestCase
{
    public function test_zero_milliseconds_is_allowed(): void
    {
        self::assertSame(0, (new Duration(0))->milliseconds);
    }

    public function test_positive_milliseconds_is_allowed(): void
    {
        self::assertSame(150, (new Duration(150))->milliseconds);
    }

    public function test_negative_milliseconds_is_rejected(): void
    {
        $this->expectException(InvalidDuration::class);

        new Duration(-1);
    }

    public function test_from_milliseconds_factory_is_equivalent_to_constructor(): void
    {
        self::assertTrue(Duration::fromMilliseconds(42)->equals(new Duration(42)));
    }

    public function test_between_calculates_millisecond_difference(): void
    {
        $start = new DateTimeImmutable('2026-01-01T00:00:00.000000Z');
        $end = new DateTimeImmutable('2026-01-01T00:00:01.250000Z');

        self::assertSame(1250, Duration::between($start, $end)->milliseconds);
    }

    public function test_between_clamps_inverted_range_to_zero(): void
    {
        $start = new DateTimeImmutable('2026-01-01T00:00:01.000000Z');
        $end = new DateTimeImmutable('2026-01-01T00:00:00.000000Z');

        self::assertSame(0, Duration::between($start, $end)->milliseconds);
    }

    public function test_equals_compares_by_value(): void
    {
        self::assertTrue((new Duration(100))->equals(new Duration(100)));
        self::assertFalse((new Duration(100))->equals(new Duration(101)));
    }
}
