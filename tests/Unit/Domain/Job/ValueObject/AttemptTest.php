<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\ValueObject;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Exception\InvalidAttempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;

final class AttemptTest extends TestCase
{
    public function test_one_is_the_minimum_allowed_value(): void
    {
        self::assertSame(1, (new Attempt(1))->value);
    }

    public function test_value_above_one_is_allowed(): void
    {
        self::assertSame(7, (new Attempt(7))->value);
    }

    public function test_zero_is_rejected(): void
    {
        $this->expectException(InvalidAttempt::class);

        new Attempt(0);
    }

    public function test_negative_is_rejected(): void
    {
        $this->expectException(InvalidAttempt::class);

        new Attempt(-3);
    }

    public function test_first_factory_returns_attempt_one(): void
    {
        self::assertSame(1, Attempt::first()->value);
    }

    public function test_next_returns_a_new_instance_with_incremented_value(): void
    {
        $current = new Attempt(2);
        $next = $current->next();

        self::assertSame(3, $next->value);
    }

    public function test_next_does_not_mutate_the_original(): void
    {
        $original = new Attempt(5);
        $original->next();

        self::assertSame(5, $original->value);
    }

    public function test_equals_compares_by_value(): void
    {
        self::assertTrue((new Attempt(2))->equals(new Attempt(2)));
        self::assertFalse((new Attempt(2))->equals(new Attempt(3)));
    }
}
