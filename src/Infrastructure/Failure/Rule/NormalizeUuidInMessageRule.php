<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Failure\Rule;

use Yammi\JobsMonitor\Domain\Failure\Contract\MessageNormalizationRule;

final class NormalizeUuidInMessageRule implements MessageNormalizationRule
{
    private const UUID_REGEX = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    public function apply(string $message): string
    {
        return preg_replace(self::UUID_REGEX, '<uuid>', $message) ?? $message;
    }
}
