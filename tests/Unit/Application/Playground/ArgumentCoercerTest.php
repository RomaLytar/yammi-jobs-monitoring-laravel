<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Playground;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Exception\InvalidPlaygroundArgument;
use Yammi\JobsMonitor\Application\Playground\ArgumentCoercer;
use Yammi\JobsMonitor\Application\Playground\ArgumentType;
use Yammi\JobsMonitor\Application\Playground\PlaygroundArgument;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Shared\ValueObject\Period;

final class ArgumentCoercerTest extends TestCase
{
    private ArgumentCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new ArgumentCoercer;
    }

    public function test_missing_optional_returns_default(): void
    {
        $arg = new PlaygroundArgument('page', ArgumentType::Integer, false, 1, '');

        self::assertSame(1, $this->coercer->coerce($arg, null));
        self::assertSame(1, $this->coercer->coerce($arg, ''));
    }

    public function test_missing_required_throws(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        $this->coercer->coerce(new PlaygroundArgument('uuid', ArgumentType::Uuid, true, null, ''), null);
    }

    public function test_integer_from_string(): void
    {
        $arg = new PlaygroundArgument('n', ArgumentType::Integer, true, null, '');

        self::assertSame(42, $this->coercer->coerce($arg, '42'));
    }

    public function test_integer_rejects_non_numeric(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        $this->coercer->coerce(new PlaygroundArgument('n', ArgumentType::Integer, true, null, ''), 'abc');
    }

    public function test_period_coerces_to_vo(): void
    {
        $arg = new PlaygroundArgument('period', ArgumentType::Period, false, 'all', '');

        $period = $this->coercer->coerce($arg, '1h');
        self::assertInstanceOf(Period::class, $period);

        $all = $this->coercer->coerce($arg, 'all');
        self::assertInstanceOf(Period::class, $all);
        self::assertTrue($all->isUnbounded());
    }

    public function test_uuid_validated(): void
    {
        $arg = new PlaygroundArgument('uuid', ArgumentType::Uuid, true, null, '');

        self::assertSame(
            '550e8400-e29b-41d4-a716-446655440001',
            $this->coercer->coerce($arg, '550E8400-E29B-41D4-A716-446655440001'),
        );
    }

    public function test_uuid_list_splits_by_comma_and_whitespace(): void
    {
        $arg = new PlaygroundArgument('uuids', ArgumentType::UuidList, true, null, '');

        $result = $this->coercer->coerce($arg, '550e8400-e29b-41d4-a716-446655440001, 550e8400-e29b-41d4-a716-446655440002');

        self::assertCount(2, $result);
    }

    public function test_fingerprint_validated(): void
    {
        $arg = new PlaygroundArgument('fp', ArgumentType::Fingerprint, true, null, '');

        self::assertSame('0123456789abcdef', $this->coercer->coerce($arg, '0123456789abcdef'));
    }

    public function test_fingerprint_rejects_bad_hex(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        $this->coercer->coerce(new PlaygroundArgument('fp', ArgumentType::Fingerprint, true, null, ''), 'xyz');
    }

    public function test_nullable_boolean_accepts_null_string(): void
    {
        $arg = new PlaygroundArgument('enabled', ArgumentType::NullableBoolean, true, null, '');

        self::assertNull($this->coercer->coerce($arg, 'null'));
        self::assertTrue($this->coercer->coerce($arg, 'true'));
        self::assertFalse($this->coercer->coerce($arg, 'false'));
    }

    public function test_json_object_parsed(): void
    {
        $arg = new PlaygroundArgument('p', ArgumentType::JsonObject, true, null, '');

        self::assertSame(
            ['data' => ['n' => 1]],
            $this->coercer->coerce($arg, '{"data":{"n":1}}'),
        );
    }

    public function test_json_object_rejects_non_object(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        $this->coercer->coerce(new PlaygroundArgument('p', ArgumentType::JsonObject, true, null, ''), '"just a string"');
    }

    public function test_email_list_validated(): void
    {
        $arg = new PlaygroundArgument('emails', ArgumentType::EmailList, true, null, '');

        $result = $this->coercer->coerce($arg, 'a@x.com, b@y.com');

        self::assertSame(['a@x.com', 'b@y.com'], $result);
    }

    public function test_email_rejects_invalid(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        $this->coercer->coerce(new PlaygroundArgument('e', ArgumentType::Email, true, null, ''), 'not-email');
    }

    public function test_job_status_coerces_to_enum(): void
    {
        $arg = new PlaygroundArgument('s', ArgumentType::JobStatus, true, null, '');

        self::assertSame(JobStatus::Failed, $this->coercer->coerce($arg, 'failed'));
    }

    public function test_job_status_rejects_unknown(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        $this->coercer->coerce(new PlaygroundArgument('s', ArgumentType::JobStatus, true, null, ''), 'weird');
    }
}
