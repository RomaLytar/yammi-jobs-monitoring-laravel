<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Failure\Rule;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeUuidInMessageRule;

final class NormalizeUuidInMessageRuleTest extends TestCase
{
    public function test_replaces_lowercase_uuid(): void
    {
        $rule = new NormalizeUuidInMessageRule;

        self::assertSame(
            'order <uuid> not found',
            $rule->apply('order 550e8400-e29b-41d4-a716-446655440000 not found'),
        );
    }

    public function test_replaces_uppercase_uuid(): void
    {
        $rule = new NormalizeUuidInMessageRule;

        self::assertSame(
            'order <uuid> not found',
            $rule->apply('order 550E8400-E29B-41D4-A716-446655440000 not found'),
        );
    }

    public function test_replaces_every_uuid_occurrence(): void
    {
        $rule = new NormalizeUuidInMessageRule;
        $input = 'a=550e8400-e29b-41d4-a716-446655440000 b=11111111-2222-3333-4444-555555555555';

        self::assertSame('a=<uuid> b=<uuid>', $rule->apply($input));
    }

    public function test_leaves_text_without_uuid_unchanged(): void
    {
        $rule = new NormalizeUuidInMessageRule;

        self::assertSame('plain error message', $rule->apply('plain error message'));
    }
}
