<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Playground;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Application\DTO\PagedResult;
use Yammi\JobsMonitor\Application\Playground\ResultSerializer;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;

final class ResultSerializerTest extends TestCase
{
    private ResultSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ResultSerializer(new PayloadRedactor);
    }

    public function test_null_is_serialized_as_null(): void
    {
        self::assertNull($this->serializer->serialize(null));
    }

    public function test_scalars_pass_through(): void
    {
        self::assertSame(42, $this->serializer->serialize(42));
        self::assertSame('hi', $this->serializer->serialize('hi'));
        self::assertTrue($this->serializer->serialize(true));
    }

    public function test_job_record_is_serialized_with_redacted_payload(): void
    {
        $record = new JobRecord(
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            Attempt::first(),
            'App\\Jobs\\X',
            'redis',
            new QueueName('default'),
            new DateTimeImmutable('2026-04-17 12:00:00'),
        );
        $record->setPayload(['data' => ['password' => 'secret', 'name' => 'Bob']]);

        $serialized = $this->serializer->serialize($record);

        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $serialized['uuid']);
        self::assertSame(1, $serialized['attempt']);
        self::assertSame('App\\Jobs\\X', $serialized['job_class']);
        self::assertSame('redis', $serialized['connection']);
        self::assertSame('default', $serialized['queue']);
        self::assertStringContainsString('2026-04-17', $serialized['started_at']);
        self::assertSame('********', $serialized['payload']['data']['password']);
        self::assertSame('Bob', $serialized['payload']['data']['name']);
    }

    public function test_paged_result_is_serialized_with_meta_and_items(): void
    {
        $paged = new PagedResult(['a', 'b'], 50, 2, 25);

        $out = $this->serializer->serialize($paged);

        self::assertSame(['a', 'b'], $out['items']);
        self::assertSame(50, $out['total']);
        self::assertSame(2, $out['page']);
        self::assertSame(25, $out['per_page']);
        self::assertSame(2, $out['total_pages']);
        self::assertFalse($out['has_more_pages']);
    }

    public function test_paged_result_items_are_serialized_recursively(): void
    {
        $record = new JobRecord(
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            Attempt::first(),
            'App\\Jobs\\X',
            'redis',
            new QueueName('default'),
            new DateTimeImmutable,
        );
        $paged = new PagedResult([$record], 1, 1, 50);

        $out = $this->serializer->serialize($paged);

        self::assertIsArray($out['items'][0]);
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $out['items'][0]['uuid']);
    }

    public function test_failure_group_is_serialized(): void
    {
        $group = new FailureGroup(
            new FailureFingerprint('0123456789abcdef'),
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable,
            5,
            ['App\\Jobs\\X'],
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            'RuntimeException',
            'boom',
            '#0 stack',
        );

        $out = $this->serializer->serialize($group);

        self::assertSame('0123456789abcdef', $out['fingerprint']);
        self::assertSame(5, $out['occurrences']);
        self::assertSame(['App\\Jobs\\X'], $out['affected_job_classes']);
    }

    public function test_bulk_result_is_serialized(): void
    {
        $result = new BulkOperationResult(3, 1, ['uuid-a' => 'err']);

        $out = $this->serializer->serialize($result);

        self::assertSame(['succeeded' => 3, 'failed' => 1, 'total' => 4, 'errors' => ['uuid-a' => 'err']], $out);
    }

    public function test_array_is_serialized_recursively(): void
    {
        $out = $this->serializer->serialize(['a' => 1, 'b' => [true, null]]);

        self::assertSame(['a' => 1, 'b' => [true, null]], $out);
    }

    public function test_datetime_is_readable_format(): void
    {
        $out = $this->serializer->serialize(new DateTimeImmutable('2026-04-17T12:34:56+00:00'));

        self::assertSame('2026-04-17 12:34:56', $out);
    }
}
