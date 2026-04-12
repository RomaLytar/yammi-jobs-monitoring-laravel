<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Classifier;

use Yammi\JobsMonitor\Domain\Job\Contract\FailureClassifier;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;

final class PatternBasedFailureClassifier implements FailureClassifier
{
    /** @var array<FailureCategory, list<string>> */
    private const PATTERNS = [
        'transient' => [
            'timeout',
            'deadlock',
            'connection refused',
            'connection reset',
            'too many connections',
            'service unavailable',
            'rate limit',
            'lock wait timeout',
            '429',
        ],
        'permanent' => [
            'validation',
            'invalid argument',
            'invalidargumentexception',
            'typeerror',
            'type error',
            'valueerror',
            'value error',
            'unexpectedvalueexception',
        ],
        'critical' => [
            'not found',
            'undefined method',
            'parseerror',
            'parse error',
        ],
    ];

    public function classify(string $exception): FailureCategory
    {
        $lower = strtolower($exception);

        foreach (self::PATTERNS as $categoryValue => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($lower, $pattern)) {
                    return FailureCategory::from($categoryValue);
                }
            }
        }

        return FailureCategory::Unknown;
    }
}
