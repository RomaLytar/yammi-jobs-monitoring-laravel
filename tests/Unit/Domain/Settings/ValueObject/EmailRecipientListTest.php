<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Settings\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidEmailRecipient;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;

final class EmailRecipientListTest extends TestCase
{
    public function test_empty_list_is_valid_and_reports_zero_count(): void
    {
        $list = new EmailRecipientList([]);

        self::assertTrue($list->isEmpty());
        self::assertSame(0, $list->count());
        self::assertSame([], $list->toArray());
    }

    public function test_constructs_from_list_of_emails_preserving_order(): void
    {
        $list = new EmailRecipientList(['ops@example.com', 'sre@example.com']);

        self::assertFalse($list->isEmpty());
        self::assertSame(2, $list->count());
        self::assertSame(['ops@example.com', 'sre@example.com'], $list->toArray());
    }

    public function test_emails_are_normalized_to_lowercase(): void
    {
        $list = new EmailRecipientList(['Ops@Example.COM']);

        self::assertSame(['ops@example.com'], $list->toArray());
    }

    public function test_surrounding_whitespace_is_trimmed(): void
    {
        $list = new EmailRecipientList(['  ops@example.com  ']);

        self::assertSame(['ops@example.com'], $list->toArray());
    }

    #[DataProvider('invalidEmailProvider')]
    public function test_invalid_email_is_rejected(string $invalid): void
    {
        $this->expectException(InvalidEmailRecipient::class);

        new EmailRecipientList([$invalid]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmailProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'missing at sign' => ['plainstring'];
        yield 'missing local part' => ['@example.com'];
        yield 'missing domain' => ['user@'];
        yield 'whitespace inside' => ['ops with space@example.com'];
    }

    public function test_duplicate_emails_are_rejected_case_insensitively(): void
    {
        $this->expectException(InvalidEmailRecipient::class);
        $this->expectExceptionMessage('duplicate');

        new EmailRecipientList(['ops@example.com', 'OPS@example.com']);
    }

    public function test_add_returns_new_instance_with_appended_email(): void
    {
        $original = new EmailRecipientList(['ops@example.com']);
        $extended = $original->add('sre@example.com');

        self::assertSame(['ops@example.com'], $original->toArray());
        self::assertSame(['ops@example.com', 'sre@example.com'], $extended->toArray());
    }

    public function test_add_rejects_duplicate(): void
    {
        $list = new EmailRecipientList(['ops@example.com']);

        $this->expectException(InvalidEmailRecipient::class);

        $list->add('Ops@Example.com');
    }

    public function test_remove_returns_new_instance_without_email(): void
    {
        $original = new EmailRecipientList(['ops@example.com', 'sre@example.com']);
        $reduced = $original->remove('OPS@example.com');

        self::assertSame(['ops@example.com', 'sre@example.com'], $original->toArray());
        self::assertSame(['sre@example.com'], $reduced->toArray());
    }

    public function test_remove_unknown_email_returns_equivalent_list(): void
    {
        $original = new EmailRecipientList(['ops@example.com']);
        $unchanged = $original->remove('unknown@example.com');

        self::assertSame(['ops@example.com'], $unchanged->toArray());
    }

    public function test_equals_compares_by_value_ignoring_case(): void
    {
        $a = new EmailRecipientList(['ops@example.com', 'sre@example.com']);
        $b = new EmailRecipientList(['OPS@example.com', 'SRE@EXAMPLE.com']);
        $c = new EmailRecipientList(['ops@example.com']);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
