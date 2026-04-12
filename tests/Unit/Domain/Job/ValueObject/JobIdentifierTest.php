<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Exception\InvalidJobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

final class JobIdentifierTest extends TestCase
{
    public function test_lowercase_uuid_is_accepted(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        self::assertSame($uuid, (new JobIdentifier($uuid))->value);
    }

    public function test_uppercase_uuid_is_normalized_to_lowercase(): void
    {
        $upper = '550E8400-E29B-41D4-A716-446655440000';
        $lower = '550e8400-e29b-41d4-a716-446655440000';

        self::assertSame($lower, (new JobIdentifier($upper))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'plain text' => ['not-a-uuid'];
        yield 'too short' => ['550e8400-e29b-41d4-a716'];
        yield 'too long' => ['550e8400-e29b-41d4-a716-446655440000-extra'];
        yield 'wrong group lengths' => ['550e840-e29b-41d4-a716-446655440000'];
        yield 'invalid hex characters' => ['550e8400-e29b-41d4-a716-44665544zzzz'];
        yield 'missing separators' => ['550e8400e29b41d4a716446655440000'];
    }

    #[DataProvider('malformedProvider')]
    public function test_malformed_uuid_is_rejected(string $value): void
    {
        $this->expectException(InvalidJobIdentifier::class);

        new JobIdentifier($value);
    }

    public function test_equals_returns_true_for_the_same_uuid_regardless_of_case(): void
    {
        $upper = new JobIdentifier('550E8400-E29B-41D4-A716-446655440000');
        $lower = new JobIdentifier('550e8400-e29b-41d4-a716-446655440000');

        self::assertTrue($upper->equals($lower));
    }

    public function test_equals_returns_false_for_different_uuids(): void
    {
        $a = new JobIdentifier('550e8400-e29b-41d4-a716-446655440000');
        $b = new JobIdentifier('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        self::assertFalse($a->equals($b));
    }
}
