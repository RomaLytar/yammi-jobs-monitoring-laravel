<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\DTO;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;

final class BulkOperationResultTest extends TestCase
{
    public function test_exposes_counts_and_errors(): void
    {
        $result = new BulkOperationResult(
            succeeded: 7,
            failed: 2,
            errors: [
                'aaa' => 'payload missing',
                'bbb' => 'already retried',
            ],
        );

        self::assertSame(7, $result->succeeded);
        self::assertSame(2, $result->failed);
        self::assertSame(
            ['aaa' => 'payload missing', 'bbb' => 'already retried'],
            $result->errors,
        );
    }

    public function test_total_is_sum_of_succeeded_and_failed(): void
    {
        $result = new BulkOperationResult(succeeded: 5, failed: 3, errors: []);

        self::assertSame(8, $result->total());
    }

    public function test_empty_result_has_zero_total(): void
    {
        $result = new BulkOperationResult(succeeded: 0, failed: 0, errors: []);

        self::assertSame(0, $result->total());
        self::assertSame([], $result->errors);
    }
}
