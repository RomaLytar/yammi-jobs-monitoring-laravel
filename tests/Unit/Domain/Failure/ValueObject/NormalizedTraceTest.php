<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Domain\Failure\ValueObject;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Domain\Failure\Exception\InvalidNormalizedTrace;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\NormalizedTrace;

final class NormalizedTraceTest extends TestCase
{
    public function test_fields_are_exposed_as_given(): void
    {
        $trace = new NormalizedTrace(
            exceptionClass: 'App\\Exceptions\\ConnectionLost',
            normalizedMessage: 'Lost connection to redis at <host>',
            firstUserFrame: 'App/Jobs/OrderImportJob.php::handle',
        );

        self::assertSame('App\\Exceptions\\ConnectionLost', $trace->exceptionClass);
        self::assertSame('Lost connection to redis at <host>', $trace->normalizedMessage);
        self::assertSame('App/Jobs/OrderImportJob.php::handle', $trace->firstUserFrame);
    }

    public function test_signature_joins_fields_with_pipe_separator(): void
    {
        $trace = new NormalizedTrace(
            exceptionClass: 'App\\Foo',
            normalizedMessage: 'msg',
            firstUserFrame: 'frame',
        );

        self::assertSame('App\\Foo|frame|msg', $trace->signature());
    }

    public function test_empty_message_is_allowed(): void
    {
        $trace = new NormalizedTrace(
            exceptionClass: 'DivisionByZeroError',
            normalizedMessage: '',
            firstUserFrame: 'App/Jobs/Calc.php::divide',
        );

        self::assertSame('', $trace->normalizedMessage);
        self::assertSame('DivisionByZeroError|App/Jobs/Calc.php::divide|', $trace->signature());
    }

    public function test_empty_exception_class_is_rejected(): void
    {
        $this->expectException(InvalidNormalizedTrace::class);

        new NormalizedTrace(
            exceptionClass: '',
            normalizedMessage: 'msg',
            firstUserFrame: 'frame',
        );
    }

    public function test_empty_first_user_frame_is_rejected(): void
    {
        $this->expectException(InvalidNormalizedTrace::class);

        new NormalizedTrace(
            exceptionClass: 'App\\Foo',
            normalizedMessage: 'msg',
            firstUserFrame: '',
        );
    }

    public function test_equals_returns_true_for_identical_traces(): void
    {
        $a = new NormalizedTrace('App\\Foo', 'msg', 'frame');
        $b = new NormalizedTrace('App\\Foo', 'msg', 'frame');

        self::assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_when_any_field_differs(): void
    {
        $base = new NormalizedTrace('App\\Foo', 'msg', 'frame');

        self::assertFalse($base->equals(new NormalizedTrace('App\\Bar', 'msg', 'frame')));
        self::assertFalse($base->equals(new NormalizedTrace('App\\Foo', 'other', 'frame')));
        self::assertFalse($base->equals(new NormalizedTrace('App\\Foo', 'msg', 'other')));
    }
}
