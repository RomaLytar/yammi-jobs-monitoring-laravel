<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support\Alert;

use RuntimeException;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

/**
 * NotificationChannel double that always throws on send().
 */
final class FailingChannel implements NotificationChannel
{
    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function send(AlertPayload $payload): void
    {
        throw new RuntimeException('transport down');
    }
}
