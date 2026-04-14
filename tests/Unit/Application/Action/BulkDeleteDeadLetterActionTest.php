<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\BulkDeleteDeadLetterAction;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;

final class BulkDeleteDeadLetterActionTest extends TestCase
{
    public function test_empty_id_list_returns_zero_result(): void
    {
        $action = new BulkDeleteDeadLetterAction($this->repo());

        $result = $action([]);

        self::assertSame(0, $result->succeeded);
        self::assertSame(0, $result->failed);
        self::assertSame([], $result->errors);
    }

    public function test_all_present_ids_are_deleted_and_counted(): void
    {
        $repo = $this->repo();
        $first = $this->storeFailedJob($repo, '550e8400-e29b-41d4-a716-446655440001');
        $second = $this->storeFailedJob($repo, '550e8400-e29b-41d4-a716-446655440002');

        $action = new BulkDeleteDeadLetterAction($repo);

        $result = $action([$first, $second]);

        self::assertSame(2, $result->succeeded);
        self::assertSame(0, $result->failed);
        self::assertCount(0, $repo->findAllAttempts($first));
        self::assertCount(0, $repo->findAllAttempts($second));
    }

    public function test_missing_id_is_recorded_as_error_without_affecting_others(): void
    {
        $repo = $this->repo();
        $present = $this->storeFailedJob($repo, '550e8400-e29b-41d4-a716-446655440001');
        $missing = new JobIdentifier('00000000-0000-0000-0000-000000000000');

        $action = new BulkDeleteDeadLetterAction($repo);

        $result = $action([$missing, $present]);

        self::assertSame(1, $result->succeeded);
        self::assertSame(1, $result->failed);
        self::assertArrayHasKey('00000000-0000-0000-0000-000000000000', $result->errors);
        self::assertCount(0, $repo->findAllAttempts($present));
    }

    public function test_large_batch_is_processed_to_completion(): void
    {
        $repo = $this->repo();
        $ids = [];
        for ($i = 1; $i <= 100; $i++) {
            $uuid = sprintf('550e8400-e29b-41d4-a716-%012d', $i);
            $ids[] = $this->storeFailedJob($repo, $uuid);
        }

        $action = new BulkDeleteDeadLetterAction($repo);

        $result = $action($ids);

        self::assertSame(100, $result->succeeded);
        self::assertSame(0, $result->failed);
    }

    private function repo(): InMemoryJobRecordRepository
    {
        return new InMemoryJobRecordRepository;
    }

    private function storeFailedJob(
        InMemoryJobRecordRepository $repo,
        string $uuid,
    ): JobIdentifier {
        $record = new JobRecord(
            id: new JobIdentifier($uuid),
            attempt: Attempt::first(),
            jobClass: 'App\\Jobs\\SendInvoice',
            connection: 'redis',
            queue: new QueueName('default'),
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $record->markAsFailed(new DateTimeImmutable('2026-01-01T00:00:01Z'), 'boom', FailureCategory::Permanent);
        $repo->save($record);

        return new JobIdentifier($uuid);
    }
}
