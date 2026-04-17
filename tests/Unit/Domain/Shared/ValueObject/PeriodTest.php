<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Shared\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Shared\Exception\InvalidPeriod;
use Yammi\JobsMonitor\Domain\Shared\ValueObject\Period;

final class PeriodTest extends TestCase
{
    public function test_none_has_no_bounds(): void
    {
        $period = Period::none();

        self::assertNull($period->from());
        self::assertNull($period->to());
        self::assertTrue($period->isUnbounded());
    }

    public function test_last_parses_minutes(): void
    {
        $now = new DateTimeImmutable('2026-04-17 12:00:00');
        $period = Period::last('30m', $now);

        self::assertEquals(new DateTimeImmutable('2026-04-17 11:30:00'), $period->from());
        self::assertEquals($now, $period->to());
        self::assertFalse($period->isUnbounded());
    }

    public function test_last_parses_hours(): void
    {
        $now = new DateTimeImmutable('2026-04-17 12:00:00');
        $period = Period::last('1h', $now);

        self::assertEquals(new DateTimeImmutable('2026-04-17 11:00:00'), $period->from());
    }

    public function test_last_parses_days(): void
    {
        $now = new DateTimeImmutable('2026-04-17 12:00:00');
        $period = Period::last('7d', $now);

        self::assertEquals(new DateTimeImmutable('2026-04-10 12:00:00'), $period->from());
    }

    public function test_last_rejects_empty_string(): void
    {
        $this->expectException(InvalidPeriod::class);

        Period::last('');
    }

    public function test_last_rejects_zero_magnitude(): void
    {
        $this->expectException(InvalidPeriod::class);

        Period::last('0h');
    }

    public function test_last_rejects_negative(): void
    {
        $this->expectException(InvalidPeriod::class);

        Period::last('-1h');
    }

    public function test_last_rejects_unknown_unit(): void
    {
        $this->expectException(InvalidPeriod::class);

        Period::last('5y');
    }

    #[DataProvider('validStringProvider')]
    public function test_parse_accepts_valid_strings(string $input): void
    {
        $period = Period::last($input);

        self::assertNotNull($period->from());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validStringProvider(): iterable
    {
        yield 'minutes' => ['15m'];
        yield 'hours' => ['24h'];
        yield 'days' => ['30d'];
        yield 'with whitespace' => [' 1h '];
    }

    public function test_between_sets_both_bounds(): void
    {
        $from = new DateTimeImmutable('2026-04-10 00:00:00');
        $to = new DateTimeImmutable('2026-04-17 00:00:00');

        $period = Period::between($from, $to);

        self::assertEquals($from, $period->from());
        self::assertEquals($to, $period->to());
    }

    public function test_between_rejects_inverted_range(): void
    {
        $this->expectException(InvalidPeriod::class);

        Period::between(
            new DateTimeImmutable('2026-04-17'),
            new DateTimeImmutable('2026-04-10'),
        );
    }

    public function test_since_sets_lower_bound_only(): void
    {
        $from = new DateTimeImmutable('2026-04-10');

        $period = Period::since($from);

        self::assertEquals($from, $period->from());
        self::assertNull($period->to());
    }

    public function test_from_value_rejects_null(): void
    {
        $this->expectException(InvalidPeriod::class);

        Period::fromValue(null);
    }

    public function test_from_value_accepts_all_string(): void
    {
        self::assertTrue(Period::fromValue('all')->isUnbounded());
    }

    public function test_last_accepts_30d(): void
    {
        $now = new DateTimeImmutable('2026-04-17 12:00:00');
        $period = Period::last('30d', $now);

        self::assertEquals(new DateTimeImmutable('2026-03-18 12:00:00'), $period->from());
        self::assertEquals($now, $period->to());
    }

    public function test_from_value_accepts_all_case_insensitive(): void
    {
        self::assertTrue(Period::fromValue('ALL')->isUnbounded());
        self::assertTrue(Period::fromValue(' all ')->isUnbounded());
    }

    public function test_from_value_accepts_period(): void
    {
        $original = Period::last('1h');

        self::assertSame($original, Period::fromValue($original));
    }

    public function test_from_value_accepts_string(): void
    {
        $period = Period::fromValue('1h');

        self::assertFalse($period->isUnbounded());
    }

    public function test_from_value_rejects_other_types(): void
    {
        $this->expectException(InvalidPeriod::class);

        /** @phpstan-ignore-next-line */
        Period::fromValue(123);
    }
}
