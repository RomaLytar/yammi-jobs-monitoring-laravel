<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Classifier;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Infrastructure\Classifier\PatternBasedFailureClassifier;

final class PatternBasedFailureClassifierTest extends TestCase
{
    private PatternBasedFailureClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new PatternBasedFailureClassifier();
    }

    /**
     * @return iterable<string, array{string, FailureCategory}>
     */
    public static function classificationProvider(): iterable
    {
        // Transient
        yield 'timeout' => ['Illuminate\Queue\MaxAttemptsExceededException: connection timeout', FailureCategory::Transient];
        yield 'deadlock' => ['PDOException: SQLSTATE[40001]: Serialization failure: deadlock detected', FailureCategory::Transient];
        yield 'connection refused' => ['RuntimeException: connection refused', FailureCategory::Transient];
        yield 'too many connections' => ['PDOException: SQLSTATE[HY000]: too many connections', FailureCategory::Transient];
        yield 'connection reset' => ['RuntimeException: connection reset by peer', FailureCategory::Transient];
        yield 'service unavailable' => ['GuzzleHttp\Exception\ServerException: 503 service unavailable', FailureCategory::Transient];
        yield 'rate limit' => ['App\Exceptions\RateLimitException: rate limit exceeded', FailureCategory::Transient];
        yield 'lock wait timeout' => ['PDOException: lock wait timeout exceeded', FailureCategory::Transient];
        yield 'too many requests 429' => ['HttpException: 429 Too Many Requests', FailureCategory::Transient];

        // Permanent
        yield 'validation exception' => ['Illuminate\Validation\ValidationException: The given data was invalid.', FailureCategory::Permanent];
        yield 'invalid argument' => ['InvalidArgumentException: Expected string, got integer', FailureCategory::Permanent];
        yield 'type error' => ['TypeError: Argument 1 must be of type string, int given', FailureCategory::Permanent];
        yield 'value error' => ['ValueError: value must be positive', FailureCategory::Permanent];
        yield 'unexpected value' => ['UnexpectedValueException: unexpected value', FailureCategory::Permanent];

        // Critical
        yield 'class not found' => ['Error: Class "App\Jobs\SendInvoice" not found', FailureCategory::Critical];
        yield 'method not found' => ['BadMethodCallException: method not found on model', FailureCategory::Critical];
        yield 'undefined method' => ['Error: Call to undefined method App\Service::process()', FailureCategory::Critical];
        yield 'parse error' => ['ParseError: syntax error, unexpected token', FailureCategory::Critical];

        // Unknown
        yield 'generic runtime' => ['RuntimeException: something unexpected happened', FailureCategory::Unknown];
        yield 'empty string' => ['', FailureCategory::Unknown];
        yield 'custom exception' => ['App\Exceptions\CustomException: custom error', FailureCategory::Unknown];
    }

    #[DataProvider('classificationProvider')]
    public function test_classifies_exception_string(
        string $exception,
        FailureCategory $expected,
    ): void {
        self::assertSame($expected, $this->classifier->classify($exception));
    }

    public function test_classification_is_case_insensitive(): void
    {
        self::assertSame(
            FailureCategory::Transient,
            $this->classifier->classify('RuntimeException: CONNECTION REFUSED'),
        );

        self::assertSame(
            FailureCategory::Critical,
            $this->classifier->classify('Error: CLASS NOT FOUND'),
        );
    }
}
