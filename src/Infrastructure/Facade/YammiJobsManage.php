<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Facade;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Application\Service\YammiJobsManageService;

/**
 * Public mutation facade — retry / delete DLQ entries and failure
 * groups, refresh anomaly baselines.
 *
 * @method static string retryDlq(string $uuid, ?array $payloadOverride = null)
 * @method static int deleteDlq(string $uuid)
 * @method static BulkOperationResult retryDlqBulk(array $uuids)
 * @method static BulkOperationResult deleteDlqBulk(array $uuids)
 * @method static ?BulkOperationResult retryFailureGroup(string $fingerprint)
 * @method static ?BulkOperationResult deleteFailureGroup(string $fingerprint)
 * @method static BulkOperationResult retryFailureGroupsBulk(array $fingerprints)
 * @method static BulkOperationResult deleteFailureGroupsBulk(array $fingerprints)
 * @method static int refreshAnomalyBaselines(int $lookbackDays = 7, ?DateTimeImmutable $now = null)
 *
 * @see YammiJobsManageService
 */
final class YammiJobsManage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return YammiJobsManageService::class;
    }
}
