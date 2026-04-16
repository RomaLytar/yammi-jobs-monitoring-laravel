<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\DTO;

/**
 * Snapshot of one notification channel's availability at request time.
 *
 * Channels are resolved from config (env-first); this DTO lets the UI
 * and API answer "which transports are live right now?" without
 * leaking the secret itself. The flag is strictly about presence of
 * the key, never about the key itself.
 */
final class ChannelStatusData
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $purpose,
        public readonly bool $configured,
        public readonly string $envVar,
    ) {}
}
