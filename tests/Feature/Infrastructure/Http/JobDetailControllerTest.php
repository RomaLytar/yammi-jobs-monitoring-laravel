<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\TestCase;

final class JobDetailControllerTest extends TestCase
{
    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    public function test_detail_returns_404_for_missing_record(): void
    {
        $response = $this->get('/jobs-monitor/'.self::UUID.'/1');

        $response->assertNotFound();
    }

    public function test_detail_shows_single_attempt_without_timeline(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $record = $this->makeRecord(1, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $record->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:01Z'));
        $repository->save($record);

        $response = $this->get('/jobs-monitor/'.self::UUID.'/1');

        $response->assertOk();
        $response->assertSee('SendInvoice');
        $response->assertDontSee('Retry Timeline');
    }

    public function test_detail_shows_timeline_when_multiple_attempts_exist(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        $first = $this->makeRecord(1, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $first->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'first failure');
        $repository->save($first);

        $second = $this->makeRecord(2, new DateTimeImmutable('2026-01-01T00:00:10Z'));
        $second->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:11Z'), 'second failure');
        $repository->save($second);

        $third = $this->makeRecord(3, new DateTimeImmutable('2026-01-01T00:00:20Z'));
        $third->markAsProcessed(new DateTimeImmutable('2026-01-01T00:00:21Z'));
        $repository->save($third);

        $response = $this->get('/jobs-monitor/'.self::UUID.'/3');

        $response->assertOk();
        $response->assertSee('Retry Timeline');
        $response->assertSee('3 attempts');
        $response->assertSee('#1');
        $response->assertSee('#2');
        $response->assertSee('#3');
        $response->assertSee('viewing');
    }

    public function test_detail_timeline_highlights_current_attempt(): void
    {
        $repository = $this->app->make(JobRecordRepository::class);

        foreach ([1, 2] as $i) {
            $record = $this->makeRecord($i, new DateTimeImmutable("2026-01-01T00:00:{$i}0Z"));
            $record->markAsFailed(new DateTimeImmutable("2026-01-01T00:00:{$i}1Z"), "fail {$i}");
            $repository->save($record);
        }

        $response = $this->get('/jobs-monitor/'.self::UUID.'/1');

        $response->assertOk();
        $response->assertSee('viewing');
    }

    private function makeRecord(int $attempt, DateTimeImmutable $startedAt): JobRecord
    {
        return new JobRecord(
            id: new JobIdentifier(self::UUID),
            attempt: new Attempt($attempt),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: $startedAt,
        );
    }
}
