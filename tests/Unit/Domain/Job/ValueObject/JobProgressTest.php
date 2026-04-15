<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Job\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobProgress;

final class JobProgressTest extends TestCase
{
    public function test_is_complete_when_current_equals_total(): void
    {
        $p = new JobProgress(10, 10, null, new DateTimeImmutable);
        self::assertTrue($p->isComplete());
        self::assertFalse($p->isPartial());
    }

    public function test_is_partial_when_some_progress_but_not_complete(): void
    {
        $p = new JobProgress(5, 10, null, new DateTimeImmutable);
        self::assertTrue($p->isPartial());
        self::assertFalse($p->isComplete());
    }

    public function test_rejects_negative_current(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JobProgress(-1, 10, null, new DateTimeImmutable);
    }

    public function test_rejects_total_below_current(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JobProgress(10, 5, null, new DateTimeImmutable);
    }

    public function test_percentage(): void
    {
        self::assertSame(50.0, (new JobProgress(5, 10, null, new DateTimeImmutable))->percentage());
        self::assertNull((new JobProgress(5, null, null, new DateTimeImmutable))->percentage());
    }
}
