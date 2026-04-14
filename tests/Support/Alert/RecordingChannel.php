<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support\Alert;

use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

/**
 * NotificationChannel double that captures every send() call.
 */
final class RecordingChannel implements NotificationChannel
{
    /** @var list<AlertPayload> */
    public array $sent = [];

    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function send(AlertPayload $payload): void
    {
        $this->sent[] = $payload;
    }
}
