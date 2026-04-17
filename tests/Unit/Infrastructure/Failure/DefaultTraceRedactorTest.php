<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Failure;

use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Infrastructure\Failure\Service\DefaultTraceRedactor;

final class DefaultTraceRedactorTest extends TestCase
{
    public function test_strips_absolute_path_prefix_in_unix_trace(): void
    {
        $trace = '#0 /home/deploy/releases/abc/vendor/laravel/framework/src/Illuminate/Foo.php(12): Foo->bar()';

        $result = (new DefaultTraceRedactor)->redact($trace);

        self::assertStringContainsString('vendor/laravel/framework/src/Illuminate/Foo.php', $result);
        self::assertStringNotContainsString('/home/deploy/releases/abc', $result);
    }

    public function test_strips_absolute_path_prefix_pointing_to_project_src(): void
    {
        $trace = 'at /var/www/shared/src/Jobs/OrderJob.php:42';

        $result = (new DefaultTraceRedactor)->redact($trace);

        self::assertStringContainsString('src/Jobs/OrderJob.php', $result);
        self::assertStringNotContainsString('/var/www/shared', $result);
    }

    public function test_redacts_password_in_connection_string(): void
    {
        $message = 'SQLSTATE: connection failed for mysql://user:s3cr3tp4ss@db.internal/app';

        $result = (new DefaultTraceRedactor)->redact($message);

        self::assertStringNotContainsString('s3cr3tp4ss', $result);
        self::assertStringContainsString('mysql://user:[REDACTED]@', $result);
    }

    public function test_redacts_bearer_token(): void
    {
        $message = 'Request failed: Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.abcDEF123.signatureXYZ';

        $result = (new DefaultTraceRedactor)->redact($message);

        self::assertStringNotContainsString('signatureXYZ', $result);
        self::assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9.abcDEF123.signatureXYZ', $result);
    }

    public function test_redacts_common_key_equals_value_tokens(): void
    {
        $message = 'failed with password=supersecret token=abc123 api_key=zzz';

        $result = (new DefaultTraceRedactor)->redact($message);

        self::assertStringNotContainsString('supersecret', $result);
        self::assertStringNotContainsString('abc123', $result);
        self::assertStringNotContainsString('zzz', $result);
        self::assertStringContainsString('password=[REDACTED]', $result);
        self::assertStringContainsString('token=[REDACTED]', $result);
        self::assertStringContainsString('api_key=[REDACTED]', $result);
    }

    public function test_redacts_aws_access_key(): void
    {
        $message = 'Credentials check failed: AKIAIOSFODNN7EXAMPLE';

        $result = (new DefaultTraceRedactor)->redact($message);

        self::assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE', $result);
        self::assertStringContainsString('[REDACTED_AWS_KEY]', $result);
    }

    public function test_empty_text_passes_through(): void
    {
        self::assertSame('', (new DefaultTraceRedactor)->redact(''));
    }
}
