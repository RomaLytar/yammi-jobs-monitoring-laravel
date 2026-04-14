<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Failure\Rule;

use Yammi\JobsMonitor\Domain\Failure\Contract\MessageNormalizationRule;

final class NormalizeNumbersInMessageRule implements MessageNormalizationRule
{
    private const LONG_NUMBER_REGEX = '/\b\d{4,}\b/';

    public function apply(string $message): string
    {
        return preg_replace(self::LONG_NUMBER_REGEX, '<n>', $message) ?? $message;
    }
}
