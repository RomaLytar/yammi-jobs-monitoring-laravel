<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Settings\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidMonitorUrl;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;

final class MonitorUrlTest extends TestCase
{
    public function test_accepts_https_url(): void
    {
        $url = new MonitorUrl('https://monitor.example.com');

        self::assertSame('https://monitor.example.com', $url->toString());
    }

    public function test_accepts_http_url(): void
    {
        $url = new MonitorUrl('http://localhost:8080');

        self::assertSame('http://localhost:8080', $url->toString());
    }

    public function test_strips_trailing_slash(): void
    {
        $url = new MonitorUrl('https://monitor.example.com/');

        self::assertSame('https://monitor.example.com', $url->toString());
    }

    public function test_preserves_path_without_trailing_slash(): void
    {
        $url = new MonitorUrl('https://example.com/jobs-monitor');

        self::assertSame('https://example.com/jobs-monitor', $url->toString());
    }

    public function test_strips_trailing_slash_from_path(): void
    {
        $url = new MonitorUrl('https://example.com/jobs-monitor/');

        self::assertSame('https://example.com/jobs-monitor', $url->toString());
    }

    public function test_trims_surrounding_whitespace(): void
    {
        $url = new MonitorUrl('  https://example.com  ');

        self::assertSame('https://example.com', $url->toString());
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_invalid_url_is_rejected(string $invalid): void
    {
        $this->expectException(InvalidMonitorUrl::class);

        new MonitorUrl($invalid);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrlProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'missing scheme' => ['monitor.example.com'];
        yield 'unsupported scheme' => ['ftp://monitor.example.com'];
        yield 'scheme without host' => ['https://'];
        yield 'gibberish' => ['not a url'];
    }

    public function test_equals_compares_by_normalized_value(): void
    {
        $a = new MonitorUrl('https://example.com/');
        $b = new MonitorUrl('https://example.com');
        $c = new MonitorUrl('https://other.example.com');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
