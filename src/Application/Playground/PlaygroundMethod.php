<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Playground;

final class PlaygroundMethod
{
    public readonly string $key;

    /**
     * @param  list<PlaygroundArgument>  $arguments
     */
    public function __construct(
        public readonly string $facade,
        public readonly string $method,
        public readonly string $description,
        public readonly array $arguments,
        public readonly bool $destructive,
        public readonly string $returns,
    ) {
        $this->key = $facade.'::'.$method;
    }
}
