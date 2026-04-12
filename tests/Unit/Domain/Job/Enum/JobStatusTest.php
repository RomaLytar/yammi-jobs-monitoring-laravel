<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;

final class JobStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{JobStatus, bool}>
     */
    public static function terminalStatusProvider(): iterable
    {
        yield 'processing is not terminal' => [JobStatus::Processing, false];
        yield 'processed is terminal' => [JobStatus::Processed, true];
        yield 'failed is terminal' => [JobStatus::Failed, true];
    }

    #[DataProvider('terminalStatusProvider')]
    public function test_is_terminal_only_for_processed_and_failed(
        JobStatus $status,
        bool $expected,
    ): void {
        self::assertSame($expected, $status->isTerminal());
    }

    /**
     * @return iterable<string, array{JobStatus, bool}>
     */
    public static function failureStatusProvider(): iterable
    {
        yield 'processing is not a failure' => [JobStatus::Processing, false];
        yield 'processed is not a failure' => [JobStatus::Processed, false];
        yield 'failed is a failure' => [JobStatus::Failed, true];
    }

    #[DataProvider('failureStatusProvider')]
    public function test_is_failure_only_for_failed(
        JobStatus $status,
        bool $expected,
    ): void {
        self::assertSame($expected, $status->isFailure());
    }

    /**
     * @return iterable<string, array{JobStatus, bool}>
     */
    public static function successStatusProvider(): iterable
    {
        yield 'processing is not a success' => [JobStatus::Processing, false];
        yield 'processed is a success' => [JobStatus::Processed, true];
        yield 'failed is not a success' => [JobStatus::Failed, false];
    }

    #[DataProvider('successStatusProvider')]
    public function test_is_success_only_for_processed(
        JobStatus $status,
        bool $expected,
    ): void {
        self::assertSame($expected, $status->isSuccess());
    }

    public function test_string_values_match_database_representation(): void
    {
        self::assertSame('processing', JobStatus::Processing->value);
        self::assertSame('processed', JobStatus::Processed->value);
        self::assertSame('failed', JobStatus::Failed->value);
    }
}
