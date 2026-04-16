<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Request;

use Illuminate\Support\Facades\Validator;
use Yammi\JobsMonitor\Infrastructure\Http\Request\AddAlertRecipientsRequest;
use Yammi\JobsMonitor\Tests\TestCase;

final class AddAlertRecipientsRequestTest extends TestCase
{
    public function test_valid_emails_array_passes(): void
    {
        $validator = $this->validate(['emails' => ['ops@example.com', 'sre@example.com']]);

        self::assertTrue($validator->passes());
    }

    public function test_single_email_passes(): void
    {
        $validator = $this->validate(['emails' => ['ops@example.com']]);

        self::assertTrue($validator->passes());
    }

    public function test_comma_separated_string_is_parsed_into_array(): void
    {
        $request = $this->buildRequest(['email' => 'ops@example.com, sre@example.com']);

        self::assertSame(['ops@example.com', 'sre@example.com'], $request->all()['emails']);
    }

    public function test_newline_separated_string_is_parsed_into_array(): void
    {
        $request = $this->buildRequest(['email' => "ops@example.com\nsre@example.com"]);

        self::assertSame(['ops@example.com', 'sre@example.com'], $request->all()['emails']);
    }

    public function test_semicolon_separated_string_is_parsed_into_array(): void
    {
        $request = $this->buildRequest(['email' => 'ops@example.com; sre@example.com']);

        self::assertSame(['ops@example.com', 'sre@example.com'], $request->all()['emails']);
    }

    public function test_whitespace_is_trimmed_from_array_entries(): void
    {
        $request = $this->buildRequest(['emails' => ['  ops@example.com  ', ' sre@example.com']]);

        self::assertSame(['ops@example.com', 'sre@example.com'], $request->all()['emails']);
    }

    public function test_empty_strings_are_filtered_from_array(): void
    {
        $request = $this->buildRequest(['emails' => ['ops@example.com', '', '  ', 'sre@example.com']]);

        self::assertSame(['ops@example.com', 'sre@example.com'], $request->all()['emails']);
    }

    public function test_missing_emails_fails_validation(): void
    {
        $validator = $this->validate([]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('emails', $validator->errors()->toArray());
    }

    public function test_empty_emails_array_fails_validation(): void
    {
        $validator = $this->validate(['emails' => []]);

        self::assertTrue($validator->fails());
    }

    public function test_invalid_email_format_fails_validation(): void
    {
        $validator = $this->validate(['emails' => ['not-an-email']]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('emails.0', $validator->errors()->toArray());
    }

    public function test_email_exceeding_max_length_fails_validation(): void
    {
        $long = str_repeat('a', 246) . '@test.com';
        self::assertGreaterThan(254, strlen($long));

        $validator = $this->validate(['emails' => [$long]]);

        self::assertTrue($validator->fails());
    }

    public function test_email_at_max_length_passes_validation(): void
    {
        $local = str_repeat('a', 242);
        $email = $local . '@example.com';
        self::assertSame(254, strlen($email));

        $validator = $this->validate(['emails' => [$email]]);

        self::assertTrue($validator->passes());
    }

    public function test_mix_of_valid_and_invalid_emails_fails(): void
    {
        $validator = $this->validate(['emails' => ['valid@example.com', 'invalid']]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('emails.1', $validator->errors()->toArray());
    }

    public function test_non_string_input_produces_empty_emails(): void
    {
        $request = $this->buildRequest(['emails' => 12345]);

        $validator = Validator::make($request->all(), $request->rules());

        self::assertTrue($validator->fails());
    }

    public function test_null_emails_key_with_string_email_key_uses_email(): void
    {
        $request = $this->buildRequest(['email' => 'ops@example.com']);

        $validator = Validator::make($request->all(), $request->rules());

        self::assertTrue($validator->passes());
    }

    public function test_emails_accessor_returns_validated_list(): void
    {
        $request = $this->buildRequest(['emails' => ['ops@example.com', 'sre@example.com']]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(\Illuminate\Routing\Redirector::class));
        $request->validateResolved();

        self::assertSame(['ops@example.com', 'sre@example.com'], $request->emails());
    }

    private function buildRequest(array $data): AddAlertRecipientsRequest
    {
        $request = AddAlertRecipientsRequest::create('/test', 'POST', $data);
        $request->setContainer($this->app);

        (new \ReflectionMethod($request, 'prepareForValidation'))->invoke($request);

        return $request;
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new AddAlertRecipientsRequest($data);
        $request->setContainer($this->app);

        return Validator::make($data, $request->rules());
    }
}
