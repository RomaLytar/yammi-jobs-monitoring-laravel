<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\ValueObject;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Exception\InvalidQueueName;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;

final class QueueNameTest extends TestCase
{
    public function test_non_empty_name_is_accepted(): void
    {
        self::assertSame('default', (new QueueName('default'))->value);
    }

    public function test_empty_string_is_rejected(): void
    {
        $this->expectException(InvalidQueueName::class);

        new QueueName('');
    }

    public function test_whitespace_only_is_rejected(): void
    {
        $this->expectException(InvalidQueueName::class);

        new QueueName("   \t\n");
    }

    public function test_leading_and_trailing_whitespace_is_trimmed(): void
    {
        self::assertSame('emails', (new QueueName('  emails  '))->value);
    }

    public function test_equals_compares_by_value(): void
    {
        self::assertTrue((new QueueName('default'))->equals(new QueueName('default')));
        self::assertFalse((new QueueName('default'))->equals(new QueueName('emails')));
    }
}
