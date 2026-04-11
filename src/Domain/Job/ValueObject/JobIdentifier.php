<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\ValueObject;

use Yammi\JobsMonitor\Domain\Job\Exception\InvalidJobIdentifier;

final class JobIdentifier
{
    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public readonly string $value;

    public function __construct(string $value)
    {
        if (preg_match(self::UUID_REGEX, $value) !== 1) {
            throw InvalidJobIdentifier::malformed($value);
        }

        $this->value = strtolower($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
