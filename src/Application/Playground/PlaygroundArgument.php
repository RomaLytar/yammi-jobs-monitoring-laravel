<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Playground;

final class PlaygroundArgument
{
    public function __construct(
        public readonly string $name,
        public readonly ArgumentType $type,
        public readonly bool $required,
        public readonly mixed $default,
        public readonly string $help,
    ) {}
}
