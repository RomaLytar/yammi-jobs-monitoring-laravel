<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;

final class PayloadRedactorTest extends TestCase
{
    private PayloadRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new PayloadRedactor;
    }

    public function test_leaves_safe_keys_untouched(): void
    {
        $payload = ['name' => 'John', 'email' => 'john@example.com', 'amount' => 100];

        $result = $this->redactor->redact($payload);

        self::assertSame($payload, $result);
    }

    public function test_redacts_password_key(): void
    {
        $result = $this->redactor->redact(['user_password' => 'secret123']);

        self::assertSame('********', $result['user_password']);
    }

    public function test_redacts_token_key(): void
    {
        $result = $this->redactor->redact(['access_token' => 'abc123']);

        self::assertSame('********', $result['access_token']);
    }

    public function test_redacts_secret_key(): void
    {
        $result = $this->redactor->redact(['client_secret' => 'xyz']);

        self::assertSame('********', $result['client_secret']);
    }

    public function test_redacts_api_key(): void
    {
        $result = $this->redactor->redact(['api_key' => 'key-123', 'apikey' => 'k2']);

        self::assertSame('********', $result['api_key']);
        self::assertSame('********', $result['apikey']);
    }

    public function test_redacts_authorization_header(): void
    {
        $result = $this->redactor->redact(['authorization' => 'Bearer abc']);

        self::assertSame('********', $result['authorization']);
    }

    public function test_is_case_insensitive(): void
    {
        $result = $this->redactor->redact(['Password' => 'x', 'API_KEY' => 'y', 'Token' => 'z']);

        self::assertSame('********', $result['Password']);
        self::assertSame('********', $result['API_KEY']);
        self::assertSame('********', $result['Token']);
    }

    public function test_redacts_nested_sensitive_keys(): void
    {
        $payload = [
            'user' => [
                'name' => 'John',
                'password' => 'secret',
                'settings' => [
                    'api_token' => 'tok-123',
                    'theme' => 'dark',
                ],
            ],
        ];

        $result = $this->redactor->redact($payload);

        self::assertSame('John', $result['user']['name']);
        self::assertSame('********', $result['user']['password']);
        self::assertSame('********', $result['user']['settings']['api_token']);
        self::assertSame('dark', $result['user']['settings']['theme']);
    }

    public function test_handles_empty_payload(): void
    {
        self::assertSame([], $this->redactor->redact([]));
    }

    public function test_redacts_credit_card_and_ssn(): void
    {
        $result = $this->redactor->redact([
            'credit_card' => '4111111111111111',
            'ssn' => '123-45-6789',
        ]);

        self::assertSame('********', $result['credit_card']);
        self::assertSame('********', $result['ssn']);
    }
}
