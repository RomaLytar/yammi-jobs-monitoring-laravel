<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Support;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Contract\ConfigReader;
use Yammi\JobsMonitor\Infrastructure\Support\MonitorTimezone;

final class MonitorTimezoneTest extends TestCase
{
    public function test_uses_configured_timezone(): void
    {
        $tz = $this->resolver(['jobs-monitor.timezone' => 'Asia/Dubai']);

        self::assertSame('Asia/Dubai', $tz->name());
        self::assertSame('Asia/Dubai', $tz->toDateTimeZone()->getName());
    }

    public function test_falls_back_to_utc_when_unset(): void
    {
        $tz = $this->resolver(['jobs-monitor.timezone' => null]);

        self::assertSame('UTC', $tz->name());
    }

    public function test_empty_string_falls_back_to_utc(): void
    {
        $tz = $this->resolver(['jobs-monitor.timezone' => '']);

        self::assertSame('UTC', $tz->name());
    }

    public function test_invalid_timezone_falls_back_to_utc(): void
    {
        $tz = $this->resolver(['jobs-monitor.timezone' => 'Mars/Phobos']);

        self::assertSame('UTC', $tz->name());
        self::assertSame('UTC', $tz->toDateTimeZone()->getName());
    }

    public function test_non_string_falls_back_to_utc(): void
    {
        $tz = $this->resolver(['jobs-monitor.timezone' => 12345]);

        self::assertSame('UTC', $tz->name());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolver(array $values): MonitorTimezone
    {
        $config = new class($values) implements ConfigReader
        {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values) {}

            public function get(string $path, mixed $default = null): mixed
            {
                return $this->values[$path] ?? $default;
            }
        };

        return new MonitorTimezone($config);
    }
}
