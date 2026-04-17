<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Service;

use DateTimeImmutable;
use Yammi\JobsMonitor\Application\Action\BulkDeleteDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\BulkRetryDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\RefreshDurationBaselinesAction;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Programmatic mutation surface behind the YammiJobsManage facade.
 * Wraps existing Actions so host apps can retry/delete DLQ entries
 * and failure groups without calling the HTTP layer.
 */
final class YammiJobsManageService
{
    private const FAILURE_GROUP_HARD_CAP = 100_000;

    private const FAILURE_GROUP_CHUNK = 1000;

    public function __construct(
        private readonly JobRecordRepository $jobs,
        private readonly FailureGroupRepository $groups,
        private readonly RetryDeadLetterJobAction $retryDlq,
        private readonly BulkRetryDeadLetterAction $bulkRetryDlq,
        private readonly BulkDeleteDeadLetterAction $bulkDeleteDlq,
        private readonly RefreshDurationBaselinesAction $refreshBaselines,
    ) {}

    /**
     * @param  array<string|int, mixed>|null  $payloadOverride  When non-null, retries with an edited payload.
     * @return string New UUID assigned to the re-dispatched job.
     */
    public function retryDlq(string $uuid, ?array $payloadOverride = null): string
    {
        return ($this->retryDlq)(new JobIdentifier($uuid), $payloadOverride);
    }

    public function deleteDlq(string $uuid): int
    {
        return $this->jobs->deleteByIdentifier(new JobIdentifier($uuid));
    }

    /**
     * @param  list<string>  $uuids
     */
    public function retryDlqBulk(array $uuids): BulkOperationResult
    {
        return ($this->bulkRetryDlq)($this->toIdentifiers($uuids));
    }

    /**
     * @param  list<string>  $uuids
     */
    public function deleteDlqBulk(array $uuids): BulkOperationResult
    {
        return ($this->bulkDeleteDlq)($this->toIdentifiers($uuids));
    }

    public function retryFailureGroup(string $fingerprint): ?BulkOperationResult
    {
        $group = $this->groups->findByFingerprint(new FailureFingerprint($fingerprint));
        if ($group === null) {
            return null;
        }

        return ($this->bulkRetryDlq)($this->collectFailureGroupIds($group->fingerprint()));
    }

    public function deleteFailureGroup(string $fingerprint): ?BulkOperationResult
    {
        $group = $this->groups->findByFingerprint(new FailureFingerprint($fingerprint));
        if ($group === null) {
            return null;
        }

        return ($this->bulkDeleteDlq)($this->collectFailureGroupIds($group->fingerprint()));
    }

    /**
     * @param  list<string>  $fingerprints
     */
    public function retryFailureGroupsBulk(array $fingerprints): BulkOperationResult
    {
        return ($this->bulkRetryDlq)($this->identifiersForFingerprints($fingerprints));
    }

    /**
     * @param  list<string>  $fingerprints
     */
    public function deleteFailureGroupsBulk(array $fingerprints): BulkOperationResult
    {
        return ($this->bulkDeleteDlq)($this->identifiersForFingerprints($fingerprints));
    }

    public function refreshAnomalyBaselines(int $lookbackDays = 7, ?DateTimeImmutable $now = null): int
    {
        return ($this->refreshBaselines)($now ?? new DateTimeImmutable, $lookbackDays);
    }

    /**
     * @param  list<string>  $uuids
     * @return list<JobIdentifier>
     */
    private function toIdentifiers(array $uuids): array
    {
        $ids = [];
        foreach ($uuids as $uuid) {
            $ids[] = new JobIdentifier($uuid);
        }

        return $ids;
    }

    /**
     * @return list<JobIdentifier>
     */
    private function collectFailureGroupIds(FailureFingerprint $fingerprint): array
    {
        $ids = [];
        $offset = 0;

        while (count($ids) < self::FAILURE_GROUP_HARD_CAP) {
            $batch = $this->jobs->listUuidsByFingerprint(
                $fingerprint,
                self::FAILURE_GROUP_CHUNK,
                $offset,
            );

            if ($batch === []) {
                break;
            }

            foreach ($batch as $uuid) {
                $ids[] = new JobIdentifier($uuid);
                if (count($ids) >= self::FAILURE_GROUP_HARD_CAP) {
                    break;
                }
            }

            if (count($batch) < self::FAILURE_GROUP_CHUNK) {
                break;
            }

            $offset += self::FAILURE_GROUP_CHUNK;
        }

        return $ids;
    }

    /**
     * @param  list<string>  $fingerprints
     * @return list<JobIdentifier>
     */
    private function identifiersForFingerprints(array $fingerprints): array
    {
        $all = [];
        foreach ($fingerprints as $hash) {
            $fp = new FailureFingerprint($hash);
            if ($this->groups->findByFingerprint($fp) === null) {
                continue;
            }
            array_push($all, ...$this->collectFailureGroupIds($fp));
        }

        return $all;
    }
}
