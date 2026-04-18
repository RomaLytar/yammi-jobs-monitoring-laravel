<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Failure\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Failure\Exception\InvalidFailureFingerprint;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;

final class FailureFingerprintTest extends TestCase
{
    public function test_valid_16_char_lowercase_hex_hash_is_accepted(): void
    {
        $hash = 'a3f1b2c4d5e6f708';

        self::assertSame($hash, (new FailureFingerprint($hash))->hash);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'too short' => ['a3f1b2c4d5e6f70'];
        yield 'too long' => ['a3f1b2c4d5e6f7089'];
        yield 'uppercase hex' => ['A3F1B2C4D5E6F708'];
        yield 'mixed case' => ['A3f1b2c4d5e6f708'];
        yield 'non-hex chars' => ['a3f1b2c4d5e6f70g'];
        yield 'whitespace' => ['a3f1b2c4d5e6f70 '];
    }

    #[DataProvider('malformedProvider')]
    public function test_malformed_hash_is_rejected(string $value): void
    {
        $this->expectException(InvalidFailureFingerprint::class);

        new FailureFingerprint($value);
    }

    public function test_equals_returns_true_for_the_same_hash(): void
    {
        $a = new FailureFingerprint('a3f1b2c4d5e6f708');
        $b = new FailureFingerprint('a3f1b2c4d5e6f708');

        self::assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_hashes(): void
    {
        $a = new FailureFingerprint('a3f1b2c4d5e6f708');
        $b = new FailureFingerprint('1111111111111111');

        self::assertFalse($a->equals($b));
    }
}
