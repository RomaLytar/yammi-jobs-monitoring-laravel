<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Alert\Exception;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Alert\Exception\InvalidAlertRule;
use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidAlertRuleTest extends TestCase
{
    public function test_extends_domain_exception(): void
    {
        self::assertInstanceOf(DomainException::class, new InvalidAlertRule('x'));
    }

    public function test_message_is_preserved(): void
    {
        $e = new InvalidAlertRule('threshold must be positive');

        self::assertSame('threshold must be positive', $e->getMessage());
    }
}
