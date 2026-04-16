<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Infrastructure\Alert\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yammi\JobsMonitor\Infrastructure\Alert\Support\HttpStatusGuard;

final class HttpStatusGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function successStatusProvider(): iterable
    {
        yield '200 OK' => [200];
        yield '201 Created' => [201];
        yield '204 No Content' => [204];
        yield '299 upper bound' => [299];
    }

    #[DataProvider('successStatusProvider')]
    public function test_does_not_throw_for_success_status(int $statusCode): void
    {
        HttpStatusGuard::assertSuccess($statusCode, 'TestChannel');

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function failureStatusProvider(): iterable
    {
        yield '199 below range' => [199];
        yield '300 redirect' => [300];
        yield '400 client error' => [400];
        yield '500 server error' => [500];
    }

    #[DataProvider('failureStatusProvider')]
    public function test_throws_for_non_success_status(int $statusCode): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Slack returned HTTP %d.', $statusCode));

        HttpStatusGuard::assertSuccess($statusCode, 'Slack');
    }
}
