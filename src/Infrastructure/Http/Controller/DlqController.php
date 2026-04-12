<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
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

    public function retry(string $uuid, RetryDeadLetterJobAction $action): RedirectResponse
    {
        $this->authorizeDestructive('retry');

        try {
            ($action)(new JobIdentifier($uuid));
        } catch (RuntimeException $e) {
            return redirect()->route('jobs-monitor.dlq')->with('error', $e->getMessage());
        }

        return redirect()->route('jobs-monitor.dlq')
            ->with('status', 'Job dispatched for retry.');
    }

    public function delete(string $uuid): RedirectResponse
    {
        $this->authorizeDestructive('delete');

        $this->repository->deleteByIdentifier(new JobIdentifier($uuid));

        return redirect()->route('jobs-monitor.dlq')
            ->with('status', 'Dead-letter entry removed.');
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
