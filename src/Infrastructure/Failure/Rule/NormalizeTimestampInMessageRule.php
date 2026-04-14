<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Failure\Rule;

use Yammi\JobsMonitor\Domain\Failure\Contract\MessageNormalizationRule;

final class NormalizeTimestampInMessageRule implements MessageNormalizationRule
{
    private const TIMESTAMP_REGEX = '/\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?/';

    public function apply(string $message): string
    {
        return preg_replace(self::TIMESTAMP_REGEX, '<timestamp>', $message) ?? $message;
    }
}
