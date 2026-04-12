<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;

final class FailureCategoryTest extends TestCase
{
    public function test_string_values_match_database_representation(): void
    {
        self::assertSame('transient', FailureCategory::Transient->value);
        self::assertSame('permanent', FailureCategory::Permanent->value);
        self::assertSame('critical', FailureCategory::Critical->value);
        self::assertSame('unknown', FailureCategory::Unknown->value);
    }

    /**
     * @return iterable<string, array{FailureCategory, bool}>
     */
    public static function retryableProvider(): iterable
    {
        yield 'transient is retryable' => [FailureCategory::Transient, true];
        yield 'permanent is not retryable' => [FailureCategory::Permanent, false];
        yield 'critical is not retryable' => [FailureCategory::Critical, false];
        yield 'unknown is not retryable' => [FailureCategory::Unknown, false];
    }

    #[DataProvider('retryableProvider')]
    public function test_is_retryable_only_for_transient(
        FailureCategory $category,
        bool $expected,
    ): void {
        self::assertSame($expected, $category->isRetryable());
    }

    public function test_label_returns_human_readable_string(): void
    {
        self::assertSame('Transient', FailureCategory::Transient->label());
        self::assertSame('Permanent', FailureCategory::Permanent->label());
        self::assertSame('Critical', FailureCategory::Critical->label());
        self::assertSame('Unknown', FailureCategory::Unknown->label());
    }
}
