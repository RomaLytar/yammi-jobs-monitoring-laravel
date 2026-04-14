<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\ValueObject;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Alert\Enum\AlertTrigger;

final class AlertPayload
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<FailureSample>  $recentFailures  Sample of failing records
     *                                               that tripped this rule.
     *                                               Empty for rules where
     *                                               samples don't apply.
     */
    public function __construct(
        public readonly AlertTrigger $trigger,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $context,
        public readonly DateTimeImmutable $triggeredAt,
        public readonly array $recentFailures = [],
        public readonly ?string $fingerprint = null,
    ) {}
}
