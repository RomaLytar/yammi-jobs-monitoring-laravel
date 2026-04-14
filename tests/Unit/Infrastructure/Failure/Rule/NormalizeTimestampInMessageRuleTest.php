<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Failure\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeTimestampInMessageRule;

final class NormalizeTimestampInMessageRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function timestampProvider(): iterable
    {
        yield 'ISO 8601 Z' => ['at 2024-01-02T03:04:05Z',             'at <timestamp>'];
        yield 'ISO 8601 offset' => ['at 2024-01-02T03:04:05+02:00',        'at <timestamp>'];
        yield 'ISO 8601 fractional' => ['at 2024-01-02T03:04:05.123Z',         'at <timestamp>'];
        yield 'space separated' => ['at 2024-01-02 03:04:05',              'at <timestamp>'];
        yield 'two timestamps' => ['from 2024-01-02T03:04:05Z to 2024-01-02T04:00:00Z', 'from <timestamp> to <timestamp>'];
    }

    #[DataProvider('timestampProvider')]
    public function test_replaces_timestamps(string $input, string $expected): void
    {
        $rule = new NormalizeTimestampInMessageRule;

        self::assertSame($expected, $rule->apply($input));
    }

    public function test_leaves_text_without_timestamp_unchanged(): void
    {
        $rule = new NormalizeTimestampInMessageRule;

        self::assertSame('boom', $rule->apply('boom'));
    }
}
