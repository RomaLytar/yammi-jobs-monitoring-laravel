<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Yammi\JobsMonitor\Application\Contract\ConfigReader;

final class LaravelConfigReader implements ConfigReader
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->config->get($path, $default);
    }
}
