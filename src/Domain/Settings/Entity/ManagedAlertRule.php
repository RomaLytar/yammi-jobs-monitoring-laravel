<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\Entity;

use Yammi\JobsMonitor\Domain\Alert\ValueObject\AlertRule;
use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidManagedAlertRule;

final class ManagedAlertRule
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $key,
        private readonly AlertRule $rule,
        private readonly bool $enabled,
        private readonly ?string $overridesBuiltIn,
        private readonly int $position,
    ) {
        $this->assertKeyNotEmpty($key);
        $this->assertPositionNonNegative($position);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function rule(): AlertRule
    {
        return $this->rule;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function overridesBuiltIn(): ?string
    {
        return $this->overridesBuiltIn;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isPersisted(): bool
    {
        return $this->id !== null;
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            key: $this->key,
            rule: $this->rule,
            enabled: $this->enabled,
            overridesBuiltIn: $this->overridesBuiltIn,
            position: $this->position,
        );
    }

    private function assertKeyNotEmpty(string $key): void
    {
        if (trim($key) !== '') {
            return;
        }

        throw new InvalidManagedAlertRule('Managed alert rule key must not be empty.');
    }

    private function assertPositionNonNegative(int $position): void
    {
        if ($position >= 0) {
            return;
        }

        throw new InvalidManagedAlertRule(sprintf(
            'Managed alert rule position must be non-negative, got %d.',
            $position,
        ));
    }
}
