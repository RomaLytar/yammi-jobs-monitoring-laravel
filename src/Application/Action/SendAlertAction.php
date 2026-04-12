<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Psr\Log\LoggerInterface;
use Throwable;
use Yammi\JobsMonitor\Domain\Alert\Contract\NotificationChannel;
use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertPayload;

/**
 * Dispatches an AlertPayload to the configured channels.
 *
 * Fail-closed: an individual channel failure is logged but never
 * propagated to the caller. An unknown channel name is logged and
 * skipped — callers cannot cause the alert pipeline to crash.
 */
final class SendAlertAction
{
    /** @var array<string, NotificationChannel> */
    private readonly array $channels;

    /**
     * @param  iterable<NotificationChannel>  $channels
     */
    public function __construct(iterable $channels, private readonly LoggerInterface $logger)
    {
        $byName = [];
        foreach ($channels as $channel) {
            $byName[$channel->name()] = $channel;
        }
        $this->channels = $byName;
    }

    /**
     * @param  list<string>  $channelNames
     */
    public function __invoke(AlertPayload $payload, array $channelNames): void
    {
        foreach ($channelNames as $name) {
            $channel = $this->channels[$name] ?? null;

            if ($channel === null) {
                $this->logger->warning(
                    sprintf('[jobs-monitor] Alert channel "%s" is not configured; skipping.', $name),
                    ['channel' => $name],
                );

                continue;
            }

            try {
                $channel->send($payload);
            } catch (Throwable $e) {
                $this->logger->error(
                    sprintf('[jobs-monitor] Failed to deliver alert via "%s" channel: %s', $name, $e->getMessage()),
                    ['channel' => $name, 'exception' => $e::class],
                );
            }
        }
    }
}
