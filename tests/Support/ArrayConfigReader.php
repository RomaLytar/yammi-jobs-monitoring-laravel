<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Application\Contract\ConfigReader;

/**
 * Dot-path config view backed by a nested array. Used by unit tests
 * that need to exercise ConfigReader-consuming Application classes
 * without booting Laravel's container.
 */
final class ArrayConfigReader implements ConfigReader
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data = []) {}

    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
