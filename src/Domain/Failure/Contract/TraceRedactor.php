<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\Contract;

/**
 * Produces a rendered-safe copy of a stack trace or exception message.
 *
 * Host applications leak sensitive data through exception strings:
 * absolute paths, DB credentials in connection strings, API keys
 * passed as header values. Anything persisted or shown in the UI
 * must flow through this contract.
 */
interface TraceRedactor
{
    public function redact(string $text): string;
}
