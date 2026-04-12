<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Contract;

use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;

/**
 * Classifies a failure exception string into a known category.
 *
 * The default implementation uses pattern matching against the exception
 * text. Host applications can replace this binding in the container to
 * provide domain-specific classification logic.
 */
interface FailureClassifier
{
    /**
     * @param  string  $exception  Exception string in "ClassName: message" format
     */
    public function classify(string $exception): FailureCategory;
}
