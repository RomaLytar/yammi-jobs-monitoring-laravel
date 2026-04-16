<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\DTO;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\DTO\SettingType;

final class SettingTypeTest extends TestCase
{
    public function test_cast_boolean_truthy(): void
    {
        self::assertTrue(SettingType::Boolean->cast('1'));
    }

    public function test_cast_boolean_falsy(): void
    {
        self::assertFalse(SettingType::Boolean->cast('0'));
    }

    public function test_cast_integer(): void
    {
        self::assertSame(42, SettingType::Integer->cast('42'));
    }

    public function test_cast_float(): void
    {
        self::assertSame(3.14, SettingType::Float->cast('3.14'));
    }

    public function test_cast_string(): void
    {
        self::assertSame('hello', SettingType::String->cast('hello'));
    }

    public function test_serialize_boolean_true(): void
    {
        self::assertSame('1', SettingType::Boolean->serialize(true));
    }

    public function test_serialize_boolean_false(): void
    {
        self::assertSame('0', SettingType::Boolean->serialize(false));
    }

    public function test_serialize_integer(): void
    {
        self::assertSame('30', SettingType::Integer->serialize(30));
    }

    public function test_serialize_float(): void
    {
        self::assertSame('0.1', SettingType::Float->serialize(0.1));
    }

    public function test_serialize_string(): void
    {
        self::assertSame('* * * * *', SettingType::String->serialize('* * * * *'));
    }
}
