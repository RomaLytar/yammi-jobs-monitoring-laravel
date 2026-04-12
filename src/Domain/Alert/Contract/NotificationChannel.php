<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\Contract;

use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

/**
 * A transport that delivers an alert to an external system (Slack, Mail, ...).
 *
 * Implementations must be resilient: transient transport failures should
 * bubble up as exceptions so the caller can log and continue. The contract
 * never guarantees at-most-once or at-least-once semantics — that is the
 * caller's responsibility.
 */
interface NotificationChannel
{
    /**
     * Machine-readable identifier used in rule configuration (e.g. "slack").
     */
    public function name(): string;

    /**
     * Deliver the payload. May throw on transport errors.
     */
    public function send(AlertPayload $payload): void;
}
