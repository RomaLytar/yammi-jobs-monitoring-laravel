<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Yammi\JobsMonitor\Application\Action\BulkDeleteDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\BulkRetryDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Application\DTO\BulkOperationResult;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Infrastructure\Http\Request\DlqBulkOperationRequest;
use Yammi\JobsMonitor\Presentation\ViewModel\DlqViewModel;

/** @internal */
final class DlqController extends Controller
{
    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly PayloadRedactor $redactor,
        private readonly ConfigRepository $config,
    ) {}

    public function __invoke(Request $request): View
    {
        $page = max(1, (int) $request->query('page', '1'));
        $maxTries = $this->maxTries();
        $retryEnabled = (bool) $this->config->get('jobs-monitor.store_payload', false);

        $viewModel = DlqViewModel::fromRepository(
            $this->repository,
            $page,
            $maxTries,
            $retryEnabled,
            $this->redactor,
        );

        return view('jobs-monitor::dlq', ['vm' => $viewModel]);
    }

    public function retry(Request $request, string $uuid, RetryDeadLetterJobAction $action): RedirectResponse
    {
        $this->authorizeDestructive('retry');

        $customPayload = null;
        $rawPayload = $request->input('payload');

        if (is_string($rawPayload) && $rawPayload !== '') {
            try {
                /** @var array<string|int, mixed> $decoded */
                $decoded = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return redirect()->route('jobs-monitor.dlq.edit', ['uuid' => $uuid])
                    ->with('error', 'Invalid JSON payload: '.$e->getMessage())
                    ->withInput();
            }

            if (! is_array($decoded)) {
                return redirect()->route('jobs-monitor.dlq.edit', ['uuid' => $uuid])
                    ->with('error', 'Payload must be a JSON object.')
                    ->withInput();
            }

            $customPayload = $decoded;
        }

        try {
            ($action)(new JobIdentifier($uuid), $customPayload);
        } catch (RuntimeException $e) {
            return redirect()->route('jobs-monitor.dlq')->with('error', $e->getMessage());
        }

        $message = $customPayload !== null
            ? 'Job dispatched for retry with edited payload.'
            : 'Job dispatched for retry.';

        return redirect()->route('jobs-monitor.dlq')->with('status', $message);
    }

    public function edit(string $uuid): View|RedirectResponse
    {
        $attempts = $this->repository->findAllAttempts(new JobIdentifier($uuid));

        if (count($attempts) === 0) {
            return redirect()->route('jobs-monitor.dlq')
                ->with('error', 'Dead-letter entry not found.');
        }

        $latest = $attempts[count($attempts) - 1];

        return view('jobs-monitor::dlq-edit', [
            'uuid' => $uuid,
            'jobClass' => $latest->jobClass,
            'queue' => $latest->queue->value,
            'connection' => $latest->connection,
            'payload' => $latest->payload(),
            'previousInput' => old('payload'),
            'retryEnabled' => (bool) $this->config->get('jobs-monitor.store_payload', false),
        ]);
    }

    public function delete(string $uuid): RedirectResponse
    {
        $this->authorizeDestructive('delete');

        $this->repository->deleteByIdentifier(new JobIdentifier($uuid));

        return redirect()->route('jobs-monitor.dlq')
            ->with('status', 'Dead-letter entry removed.');
    }

    public function bulkRetry(
        DlqBulkOperationRequest $request,
        BulkRetryDeadLetterAction $action,
    ): JsonResponse {
        $this->authorizeDestructive('retry');

        return $this->bulkResponse($action($request->identifiers()));
    }

    public function bulkDelete(
        DlqBulkOperationRequest $request,
        BulkDeleteDeadLetterAction $action,
    ): JsonResponse {
        $this->authorizeDestructive('delete');

        return $this->bulkResponse($action($request->identifiers()));
    }

    private function bulkResponse(BulkOperationResult $result): JsonResponse
    {
        return response()->json([
            'succeeded' => $result->succeeded,
            'failed' => $result->failed,
            'errors' => (object) $result->errors,
            'total' => $result->total(),
        ]);
    }

    private function maxTries(): int
    {
        $value = $this->config->get('jobs-monitor.max_tries', 3);

        return is_int($value) && $value > 0 ? $value : 3;
    }

    private function authorizeDestructive(string $action): void
    {
        /** @var string|null $ability */
        $ability = $this->config->get('jobs-monitor.dlq.authorization');

        if ($ability === null) {
            return;
        }

        if (! Gate::check($ability, $action)) {
            abort(403);
        }
    }
}
