<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support\Alert;

use Psr\Log\AbstractLogger;

/**
 * PSR logger double that discards every log call.
 */
final class NullLogger extends AbstractLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void {}
}
