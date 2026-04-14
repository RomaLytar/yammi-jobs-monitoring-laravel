<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Failure\Rule;

use Yammi\JobsMonitor\Domain\Failure\Contract\MessageNormalizationRule;

final class NormalizeEmailInMessageRule implements MessageNormalizationRule
{
    private const EMAIL_REGEX = '/[\w.+-]+@[\w.-]+\.\w{2,}/';

    public function apply(string $message): string
    {
        return preg_replace(self::EMAIL_REGEX, '<email>', $message) ?? $message;
    }
}
