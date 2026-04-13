<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\ValueObject;

use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidMonitorUrl;

final class MonitorUrl
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private readonly string $value;

    public function __construct(string $url)
    {
        $normalized = $this->normalize($url);
        $this->assertValidUrl($normalized);
        $this->value = $normalized;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function normalize(string $url): string
    {
        return rtrim(trim($url), '/');
    }

    private function assertValidUrl(string $url): void
    {
        if ($url === '') {
            throw new InvalidMonitorUrl('Monitor URL must not be empty.');
        }

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw new InvalidMonitorUrl(sprintf(
                'Monitor URL "%s" is not a valid absolute URL.',
                $url,
            ));
        }

        if (! in_array($parsed['scheme'], self::ALLOWED_SCHEMES, true)) {
            throw new InvalidMonitorUrl(sprintf(
                'Monitor URL scheme "%s" is not supported. Use http or https.',
                $parsed['scheme'],
            ));
        }
    }
}
