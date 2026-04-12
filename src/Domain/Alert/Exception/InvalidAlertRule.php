<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Alert\Exception;

use Yammi\JobsMonitor\Domain\Exception\DomainException;

final class InvalidAlertRule extends DomainException
{
    public static function emptyChannels(): self
    {
        return new self('Alert rule must have at least one notification channel.');
    }

    public static function nonPositiveThreshold(int $value): self
    {
        return new self(sprintf('Alert rule threshold must be a positive integer, got %d.', $value));
    }

    public static function nonPositiveCooldown(int $value): self
    {
        return new self(sprintf('Alert rule cooldown_minutes must be a positive integer, got %d.', $value));
    }

    public static function invalidWindow(string $value): self
    {
        return new self(sprintf(
            'Alert rule window "%s" is not a valid duration. Expected format like "5m", "1h", "2d".',
            $value,
        ));
    }

    public static function missingTriggerValue(string $trigger): self
    {
        return new self(sprintf('Alert rule trigger "%s" requires a "value" field.', $trigger));
    }

    public static function unexpectedTriggerValue(string $trigger): self
    {
        return new self(sprintf('Alert rule trigger "%s" does not accept a "value" field.', $trigger));
    }

    public static function missingWindow(string $trigger): self
    {
        return new self(sprintf('Alert rule trigger "%s" requires a "window" field.', $trigger));
    }
}
