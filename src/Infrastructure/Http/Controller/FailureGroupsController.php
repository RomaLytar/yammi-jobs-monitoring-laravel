<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Yammi\JobsMonitor\Application\Action\BulkDeleteDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\BulkRetryDeadLetterAction;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Domain\Failure\Entity\FailureGroup;
use Yammi\JobsMonitor\Domain\Failure\Exception\InvalidFailureFingerprint;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Domain\Failure\ValueObject\FailureFingerprint;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/** @internal */
final class FailureGroupsController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 200;

    private const BULK_MAX_IDS = 1000;

    public function __construct(
        private readonly FailureGroupRepository $groups,
        private readonly JobRecordRepository $jobs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', (string) self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) $request->query('page', '1'));

        $items = $this->groups->listOrderedByLastSeen($perPage, ($page - 1) * $perPage);
        $total = $this->groups->countAll();

        return new JsonResponse([
            'data' => array_map(fn (FailureGroup $g) => $this->summarize($g), $items),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function show(string $fingerprint): JsonResponse
    {
        $group = $this->findOrNull($fingerprint);

        if ($group === null) {
            return new JsonResponse(['error' => 'Failure group not found.'], 404);
        }

        $uuids = $this->jobs->listUuidsByFingerprint($group->fingerprint(), self::BULK_MAX_IDS);

        return new JsonResponse([
            'data' => $this->summarize($group) + [
                'sample_stack_trace' => $group->sampleStackTrace(),
                'job_uuids' => $uuids,
                'job_uuids_truncated' => count($uuids) >= self::BULK_MAX_IDS,
            ],
        ]);
    }

    public function bulkRetry(string $fingerprint, BulkRetryDeadLetterAction $action): JsonResponse
    {
        if (! $this->authorizeDestructive('retry')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        $group = $this->findOrNull($fingerprint);

        if ($group === null) {
            return new JsonResponse(['error' => 'Failure group not found.'], 404);
        }

        $ids = $this->collectIdentifiers($group->fingerprint());

        return $this->bulkResponse($action($ids));
    }

    public function bulkDelete(string $fingerprint, BulkDeleteDeadLetterAction $action): JsonResponse
    {
        if (! $this->authorizeDestructive('delete')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        $group = $this->findOrNull($fingerprint);

        if ($group === null) {
            return new JsonResponse(['error' => 'Failure group not found.'], 404);
        }

        $ids = $this->collectIdentifiers($group->fingerprint());

        return $this->bulkResponse($action($ids));
    }

    private function findOrNull(string $fingerprint): ?FailureGroup
    {
        try {
            $vo = new FailureFingerprint($fingerprint);
        } catch (InvalidFailureFingerprint) {
            return null;
        }

        return $this->groups->findByFingerprint($vo);
    }

    /**
     * @return array{fingerprint: string, occurrences: int, affected_job_classes: list<string>, first_seen_at: string, last_seen_at: string, sample_exception_class: string, sample_message: string, last_job_uuid: string}
     */
    private function summarize(FailureGroup $group): array
    {
        return [
            'fingerprint' => $group->fingerprint()->hash,
            'occurrences' => $group->occurrences(),
            'affected_job_classes' => $group->affectedJobClasses(),
            'first_seen_at' => $group->firstSeenAt()->format(DATE_ATOM),
            'last_seen_at' => $group->lastSeenAt()->format(DATE_ATOM),
            'sample_exception_class' => $group->sampleExceptionClass(),
            'sample_message' => $group->sampleMessage(),
            'last_job_uuid' => $group->lastJobId()->value,
        ];
    }

    /**
     * @return list<JobIdentifier>
     */
    private function collectIdentifiers(FailureFingerprint $fingerprint): array
    {
        $uuids = $this->jobs->listUuidsByFingerprint($fingerprint, self::BULK_MAX_IDS);

        return array_values(array_map(
            static fn (string $uuid): JobIdentifier => new JobIdentifier($uuid),
            $uuids,
        ));
    }

    private function bulkResponse(BulkOperationResult $result): JsonResponse
    {
        return new JsonResponse([
            'succeeded' => $result->succeeded,
            'failed' => $result->failed,
            'errors' => (object) $result->errors,
            'total' => $result->total(),
        ]);
    }

    private function authorizeDestructive(string $action): bool
    {
        /** @var string|null $ability */
        $ability = config('jobs-monitor.dlq.authorization');

        if ($ability === null) {
            return true;
        }

        return Gate::check($ability, $action);
    }
}
