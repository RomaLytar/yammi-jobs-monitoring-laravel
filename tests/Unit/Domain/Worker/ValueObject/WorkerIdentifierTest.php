<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Worker\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Worker\Exception\InvalidWorkerIdentifier;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;

final class WorkerIdentifierTest extends TestCase
{
    public function test_trims_surrounding_whitespace(): void
    {
        self::assertSame('host-a:1234', (new WorkerIdentifier("  host-a:1234\n"))->value);
    }

    public function test_keeps_internal_separators_verbatim(): void
    {
        $id = new WorkerIdentifier('redis:default:host-a:1234');

        self::assertSame('redis:default:host-a:1234', $id->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ["   \t\n"];
    }

    #[DataProvider('blankProvider')]
    public function test_rejects_blank_identifier(string $value): void
    {
        $this->expectException(InvalidWorkerIdentifier::class);

        new WorkerIdentifier($value);
    }

    public function test_rejects_identifier_longer_than_191_chars(): void
    {
        $this->expectException(InvalidWorkerIdentifier::class);

        new WorkerIdentifier(str_repeat('x', 192));
    }

    public function test_accepts_identifier_exactly_191_chars(): void
    {
        $max = str_repeat('x', 191);

        self::assertSame($max, (new WorkerIdentifier($max))->value);
    }

    public function test_equals_compares_by_value(): void
    {
        $a = new WorkerIdentifier('host-a:1234');
        $b = new WorkerIdentifier('host-a:1234');
        $c = new WorkerIdentifier('host-b:9999');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
