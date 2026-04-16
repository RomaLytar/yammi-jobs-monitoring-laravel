<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Support;

use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

/**
 * Test double that keeps every AlertPayload it was sent. Useful for
 * assertions on trigger/resolve transitions emitted by actions.
 */
final class RecordingNotificationChannel implements NotificationChannel
{
    /** @var list<AlertPayload> */
    public array $sent = [];

    public function __construct(private readonly string $channelName = 'slack') {}

    public function name(): string
    {
        return $this->channelName;
    }

    public function send(AlertPayload $payload): void
    {
        $this->sent[] = $payload;
    }
}
