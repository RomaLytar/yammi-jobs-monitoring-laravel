<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Alert\Support;

use RuntimeException;

/** @internal */
final class HttpStatusGuard
{
    public static function assertSuccess(int $statusCode, string $channelLabel): void
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        throw new RuntimeException(
            sprintf('%s returned HTTP %d.', $channelLabel, $statusCode),
        );
    }
}
