<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Request;

use Illuminate\Support\Facades\Validator;
use Yammi\JobsMonitor\Infrastructure\Http\Request\ToggleAlertsRequest;
use Yammi\JobsMonitor\Tests\TestCase;

final class ToggleAlertsRequestTest extends TestCase
{
    public function test_enabled_true_passes(): void
    {
        $validator = $this->validate(['enabled' => true]);

        self::assertTrue($validator->passes());
    }

    public function test_enabled_false_passes(): void
    {
        $validator = $this->validate(['enabled' => false]);

        self::assertTrue($validator->passes());
    }

    public function test_enabled_string_one_passes(): void
    {
        $validator = $this->validate(['enabled' => '1']);

        self::assertTrue($validator->passes());
    }

    public function test_enabled_string_zero_passes(): void
    {
        $validator = $this->validate(['enabled' => '0']);

        self::assertTrue($validator->passes());
    }

    public function test_missing_enabled_fails(): void
    {
        $validator = $this->validate([]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('enabled', $validator->errors()->toArray());
    }

    public function test_non_boolean_string_fails(): void
    {
        $validator = $this->validate(['enabled' => 'yes']);

        self::assertTrue($validator->fails());
    }

    public function test_integer_two_fails(): void
    {
        $validator = $this->validate(['enabled' => 2]);

        self::assertTrue($validator->fails());
    }

    public function test_enabled_accessor_returns_true(): void
    {
        $request = new ToggleAlertsRequest(['enabled' => '1']);
        $request->setContainer($this->app);

        self::assertTrue($request->enabled());
    }

    public function test_enabled_accessor_returns_false(): void
    {
        $request = new ToggleAlertsRequest(['enabled' => '0']);
        $request->setContainer($this->app);

        self::assertFalse($request->enabled());
    }

    public function test_authorize_always_returns_true(): void
    {
        $request = new ToggleAlertsRequest;

        self::assertTrue($request->authorize());
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new ToggleAlertsRequest($data);
        $request->setContainer($this->app);

        return Validator::make($data, $request->rules());
    }
}
