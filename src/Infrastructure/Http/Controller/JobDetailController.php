<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
<<<<<<< HEAD
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
=======
>>>>>>> origin/main
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/** @internal */
final class JobDetailController extends Controller
{
    public function __invoke(
        string $uuid,
        int $attempt,
        JobRecordRepository $repository,
        ConfigRepository $config,
<<<<<<< HEAD
        PayloadRedactor $redactor,
=======
>>>>>>> origin/main
    ): View|Response {
        $identifier = new JobIdentifier($uuid);

        $record = $repository->findByIdentifierAndAttempt(
            $identifier,
            new Attempt($attempt),
        );

        if ($record === null) {
            abort(404);
        }

        $attempts = $repository->findAllAttempts($identifier);

<<<<<<< HEAD
        $payload = $record->payload();
        if ($payload !== null) {
            $payload = $redactor->redact($payload);
        }

=======
>>>>>>> origin/main
        return view('jobs-monitor::detail', [
            'record' => $record,
            'attempts' => $attempts,
            'currentAttempt' => $attempt,
            'retryEnabled' => (bool) $config->get('jobs-monitor.store_payload', false),
            'canRetry' => $this->canInvokeDestructive($config, 'retry'),
            'canDelete' => $this->canInvokeDestructive($config, 'delete'),
<<<<<<< HEAD
            'redactedPayload' => $payload,
=======
>>>>>>> origin/main
        ]);
    }

    private function canInvokeDestructive(ConfigRepository $config, string $action): bool
    {
        $ability = $config->get('jobs-monitor.dlq.authorization');

        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return Gate::check($ability, $action);
    }
}
