<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    private const CHUNK_SIZE = 1000;

    private const HARD_CAP = 100_000;

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

        $uuids = $this->jobs->listUuidsByFingerprint($group->fingerprint(), self::HARD_CAP);

        return new JsonResponse([
            'data' => $this->summarize($group) + [
                'sample_stack_trace' => $group->sampleStackTrace(),
                'job_uuids' => $uuids,
                'job_uuids_truncated' => count($uuids) >= self::HARD_CAP,
            ],
        ]);
    }

    public function bulkRetry(string $fingerprint, BulkRetryDeadLetterAction $action): RedirectResponse
    {
        if (! $this->authorizeDestructive('retry')) {
            return $this->backToPage()->with('error', 'You are not authorised to retry failure groups.');
        }

        $group = $this->findOrNull($fingerprint);

        if ($group === null) {
            return $this->backToPage()->with('error', 'Failure group not found.');
        }

        $ids = $this->collectIdentifiers($group->fingerprint());
        $result = $action($ids);

        return $this->backToPage()->with(
            $result->failed === 0 ? 'status' : 'error',
            $this->flashMessage('Re-dispatched', $result, $group->fingerprint()->hash),
        );
    }

    public function bulkDelete(string $fingerprint, BulkDeleteDeadLetterAction $action): RedirectResponse
    {
        if (! $this->authorizeDestructive('delete')) {
            return $this->backToPage()->with('error', 'You are not authorised to delete failure groups.');
        }

        $group = $this->findOrNull($fingerprint);

        if ($group === null) {
            return $this->backToPage()->with('error', 'Failure group not found.');
        }

        $ids = $this->collectIdentifiers($group->fingerprint());
        $result = $action($ids);

        return $this->backToPage()->with(
            $result->failed === 0 ? 'status' : 'error',
            $this->flashMessage('Deleted', $result, $group->fingerprint()->hash),
        );
    }

    private function backToPage(): RedirectResponse
    {
        return redirect()->route('jobs-monitor.failures.groups.page');
    }

    private function flashMessage(string $verb, BulkOperationResult $result, string $fingerprint): string
    {
        if ($result->failed === 0) {
            return sprintf('%s %d job(s) in group %s.', $verb, $result->succeeded, $fingerprint);
        }

        return sprintf(
            '%s %d job(s) in group %s; %d failed.',
            $verb,
            $result->succeeded,
            $fingerprint,
            $result->failed,
        );
    }

    public function bulkCandidates(): JsonResponse
    {
        $items = $this->groups->listOrderedByLastSeen(self::HARD_CAP, 0);
        $total = $this->groups->countAll();

        $ids = array_values(array_map(
            static fn (FailureGroup $g): string => $g->fingerprint()->hash,
            $items,
        ));

        return new JsonResponse([
            'ids' => $ids,
            'total' => $total,
            'truncated' => $total > count($ids),
        ]);
    }

    public function bulkRetryMany(Request $request, BulkRetryDeadLetterAction $action): JsonResponse
    {
        if (! $this->authorizeDestructive('retry')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        return $this->bulkResponse($action($this->collectFromRequest($request)));
    }

    public function bulkDeleteMany(Request $request, BulkDeleteDeadLetterAction $action): JsonResponse
    {
        if (! $this->authorizeDestructive('delete')) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        return $this->bulkResponse($action($this->collectFromRequest($request)));
    }

    /**
     * @return list<JobIdentifier>
     */
    private function collectFromRequest(Request $request): array
    {
        /** @var array<int, mixed> $rawIds */
        $rawIds = (array) $request->input('ids', []);

        $ids = [];
        foreach ($rawIds as $hash) {
            if (! is_string($hash)) {
                continue;
            }
            try {
                $vo = new FailureFingerprint($hash);
            } catch (InvalidFailureFingerprint) {
                continue;
            }

            foreach ($this->jobs->listUuidsByFingerprint($vo, self::HARD_CAP) as $uuid) {
                $ids[$uuid] = new JobIdentifier($uuid);
            }
        }

        return array_values($ids);
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
     * Pulls every job UUID belonging to the fingerprint in CHUNK_SIZE
     * batches, capped at HARD_CAP overall. Avoids one massive SELECT
     * for a runaway group while still covering normal-size ones in full.
     *
     * @return list<JobIdentifier>
     */
    private function collectIdentifiers(FailureFingerprint $fingerprint): array
    {
        $ids = [];
        $offset = 0;

        while (count($ids) < self::HARD_CAP) {
            $batch = $this->jobs->listUuidsByFingerprint(
                $fingerprint,
                self::CHUNK_SIZE,
                $offset,
            );

            if ($batch === []) {
                break;
            }

            foreach ($batch as $uuid) {
                $ids[] = new JobIdentifier($uuid);
                if (count($ids) >= self::HARD_CAP) {
                    break;
                }
            }

            if (count($batch) < self::CHUNK_SIZE) {
                break;
            }

            $offset += self::CHUNK_SIZE;
        }

        return $ids;
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
            return auth()->check();
        }

        return Gate::check($ability, $action);
    }
}
