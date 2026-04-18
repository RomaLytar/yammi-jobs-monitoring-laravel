<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Failure\Rule;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeNumbersInMessageRule;

final class NormalizeNumbersInMessageRuleTest extends TestCase
{
    public function test_replaces_long_digit_sequences(): void
    {
        $rule = new NormalizeNumbersInMessageRule;

        self::assertSame(
            'order <n> failed',
            $rule->apply('order 123456 failed'),
        );
    }

    public function test_leaves_short_numbers_alone(): void
    {
        $rule = new NormalizeNumbersInMessageRule;

        self::assertSame(
            'status 500 for 42 retries',
            $rule->apply('status 500 for 42 retries'),
        );
    }

    public function test_replaces_every_long_number(): void
    {
        $rule = new NormalizeNumbersInMessageRule;

        self::assertSame(
            'ids <n> and <n>',
            $rule->apply('ids 100000 and 200000'),
        );
    }
}
