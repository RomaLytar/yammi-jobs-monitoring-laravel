<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Worker\Enum;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Worker\Enum\WorkerStatus;

final class WorkerStatusTest extends TestCase
{
    public function test_alive_flag(): void
    {
        self::assertTrue(WorkerStatus::Alive->isAlive());
        self::assertFalse(WorkerStatus::Silent->isAlive());
        self::assertFalse(WorkerStatus::Dead->isAlive());
    }

    public function test_silent_flag(): void
    {
        self::assertFalse(WorkerStatus::Alive->isSilent());
        self::assertTrue(WorkerStatus::Silent->isSilent());
        self::assertFalse(WorkerStatus::Dead->isSilent());
    }

    public function test_dead_flag(): void
    {
        self::assertFalse(WorkerStatus::Alive->isDead());
        self::assertFalse(WorkerStatus::Silent->isDead());
        self::assertTrue(WorkerStatus::Dead->isDead());
    }

    public function test_labels_are_human_readable(): void
    {
        self::assertSame('Alive', WorkerStatus::Alive->label());
        self::assertSame('Silent', WorkerStatus::Silent->label());
        self::assertSame('Dead', WorkerStatus::Dead->label());
    }

    public function test_values_are_stable_strings(): void
    {
        self::assertSame('alive', WorkerStatus::Alive->value);
        self::assertSame('silent', WorkerStatus::Silent->value);
        self::assertSame('dead', WorkerStatus::Dead->value);
    }
}
