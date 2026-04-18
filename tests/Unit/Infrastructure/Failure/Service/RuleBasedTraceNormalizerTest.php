<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Failure\Service;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeEmailInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeNumbersInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeTimestampInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Rule\NormalizeUuidInMessageRule;
use Yammi\JobsMonitor\Infrastructure\Failure\Service\RuleBasedTraceNormalizer;

final class RuleBasedTraceNormalizerTest extends TestCase
{
    public function test_returns_exception_class_verbatim(): void
    {
        $normalizer = new RuleBasedTraceNormalizer(rules: []);

        $trace = $normalizer->normalize(
            exceptionClass: 'App\\Exceptions\\ConnectionLost',
            message: 'boom',
            stackTraceAsString: '#0 /app/app/Jobs/X.php(12): App\\Jobs\\X->handle()',
        );

        self::assertSame('App\\Exceptions\\ConnectionLost', $trace->exceptionClass);
    }

    public function test_applies_all_message_rules_in_order(): void
    {
        $normalizer = new RuleBasedTraceNormalizer(rules: [
            new NormalizeUuidInMessageRule,
            new NormalizeEmailInMessageRule,
            new NormalizeTimestampInMessageRule,
            new NormalizeNumbersInMessageRule,
        ]);

        $trace = $normalizer->normalize(
            exceptionClass: 'E',
            message: 'user john@x.co order 550e8400-e29b-41d4-a716-446655440000 at 2024-01-02T03:04:05Z id=999999',
            stackTraceAsString: '#0 /app/app/Jobs/X.php(1): App\\Jobs\\X->handle()',
        );

        self::assertSame('user <email> order <uuid> at <timestamp> id=<n>', $trace->normalizedMessage);
    }

    public function test_first_user_frame_is_extracted_and_line_number_stripped(): void
    {
        $normalizer = new RuleBasedTraceNormalizer(rules: []);

        $trace = $normalizer->normalize(
            exceptionClass: 'E',
            message: 'm',
            stackTraceAsString: implode("\n", [
                '#0 /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php(812): PDO->prepare()',
                '#1 /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php(520): Illuminate\\Database\\Connection->statement()',
                '#2 /app/app/Jobs/OrderImportJob.php(42): App\\Jobs\\OrderImportJob->handle()',
                '#3 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(93): ...',
            ]),
        );

        self::assertSame('app/Jobs/OrderImportJob.php::App\\Jobs\\OrderImportJob@handle', $trace->firstUserFrame);
    }

    public function test_vendor_sentinel_used_when_no_user_frame_is_present(): void
    {
        $normalizer = new RuleBasedTraceNormalizer(rules: []);

        $trace = $normalizer->normalize(
            exceptionClass: 'E',
            message: 'm',
            stackTraceAsString: implode("\n", [
                '#0 /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php(812): PDO->prepare()',
                '#1 /app/vendor/symfony/console/Application.php(100): Symfony\\Console->run()',
            ]),
        );

        self::assertSame('<vendor>', $trace->firstUserFrame);
    }

    public function test_unknown_sentinel_used_when_stack_trace_is_unparseable(): void
    {
        $normalizer = new RuleBasedTraceNormalizer(rules: []);

        $trace = $normalizer->normalize(
            exceptionClass: 'E',
            message: 'm',
            stackTraceAsString: '',
        );

        self::assertSame('<unknown>', $trace->firstUserFrame);
    }
}
