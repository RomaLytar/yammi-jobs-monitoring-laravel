<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Worker\Enum;

enum WorkerStatus: string
{
    case Alive = 'alive';
    case Silent = 'silent';
    case Dead = 'dead';

    public function isAlive(): bool
    {
        return $this === self::Alive;
    }

    public function isSilent(): bool
    {
        return $this === self::Silent;
    }

    public function isDead(): bool
    {
        return $this === self::Dead;
    }

    public function label(): string
    {
        return match ($this) {
            self::Alive => 'Alive',
            self::Silent => 'Silent',
            self::Dead => 'Dead',
        };
    }
}
