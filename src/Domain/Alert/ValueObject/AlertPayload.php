<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\ValueObject;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;

final class AlertPayload
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly AlertTrigger $trigger,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $context,
        public readonly DateTimeImmutable $triggeredAt,
    ) {}
}
