<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Failure\ValueObject;

use Yammi\JobsMonitor\Domain\Failure\Exception\InvalidFailureFingerprint;

final class FailureFingerprint
{
    private const HASH_REGEX = '/^[0-9a-f]{16}$/';

    public readonly string $hash;

    public function __construct(string $hash)
    {
        if (preg_match(self::HASH_REGEX, $hash) !== 1) {
            throw new InvalidFailureFingerprint(sprintf(
                'Fingerprint must be 16 lowercase hex characters, got "%s".',
                $hash,
            ));
        }

        $this->hash = $hash;
    }

    public function equals(self $other): bool
    {
        return $this->hash === $other->hash;
    }
}
