<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use Yammi\JobsMonitor\Application\Action\RecordScheduledTaskRunAction;
use Yammi\JobsMonitor\Application\DTO\ScheduledTaskRunData;
use Yammi\JobsMonitor\Domain\Scheduler\Enum\ScheduledTaskStatus;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\ScheduledTaskRunModel;
use Yammi\JobsMonitor\Presentation\ViewModel\ScheduledTasksViewModel;

/** @internal */
final class ScheduledTasksController extends Controller
{
    public function __construct(
        private readonly ScheduledTaskRunRepository $repository,
        private readonly RecordScheduledTaskRunAction $recorder,
    ) {}

    public function __invoke(Request $request): View
    {
        $vm = ScheduledTasksViewModel::fromRepository(
            repository: $this->repository,
            page: max(1, (int) $request->query('page', '1')),
            status: trim((string) $request->query('status', '')),
            search: trim((string) $request->query('search', '')),
            sort: (string) $request->query('sort', 'started_at'),
            dir: (string) $request->query('dir', 'desc'),
            failedPage: max(1, (int) $request->query('fpage', '1')),
        );

        return view('jobs-monitor::scheduled-tasks', ['vm' => $vm]);
    }

    /**
     * Re-runs the artisan command behind a stored scheduled-task record.
     * Captures stdout into the response so the operator sees what happened.
     */
    public function retry(Request $request, int $id): RedirectResponse
    {
        $row = ScheduledTaskRunModel::query()->find($id);
        if ($row === null) {
            return back()->withErrors(['retry' => 'Scheduled run not found.']);
        }

        $command = $this->extractArtisanCommand($row->command, $row->task_name);
        if ($command === null) {
            return back()->withErrors([
                'retry' => 'This run is not an artisan command, cannot re-run from here.',
            ]);
        }

        $startedAt = new DateTimeImmutable;

        try {
            $exitCode = Artisan::call($command);
            $output = trim(Artisan::output());
            $finishedAt = new DateTimeImmutable;
            $succeeded = $exitCode === 0;

            $this->recordRetryRun($row, $startedAt, $finishedAt, $succeeded, $exitCode, $output, null);

            return back()->with('status', sprintf(
                'Re-ran "%s" — exit %d. %s',
                $command,
                $exitCode,
                $output === '' ? '' : "Output:\n".$output,
            ));
        } catch (Throwable $e) {
            $this->recordRetryRun(
                row: $row,
                startedAt: $startedAt,
                finishedAt: new DateTimeImmutable,
                succeeded: false,
                exitCode: null,
                output: null,
                exception: sprintf('%s: %s', $e::class, $e->getMessage()),
            );

            return back()->withErrors([
                'retry' => sprintf('Re-run failed: %s', $e->getMessage()),
            ]);
        }
    }

    /**
     * Persist the retry as a fresh run on the same mutex so the dashboard
     * counters and run list reflect the manual execution.
     */
    private function recordRetryRun(
        ScheduledTaskRunModel $row,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $finishedAt,
        bool $succeeded,
        ?int $exitCode,
        ?string $output,
        ?string $exception,
    ): void {
        ($this->recorder)(new ScheduledTaskRunData(
            mutex: $row->mutex,
            taskName: $row->task_name.' · manual retry',
            expression: $row->expression,
            timezone: $row->timezone,
            status: $succeeded ? ScheduledTaskStatus::Success : ScheduledTaskStatus::Failed,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            exitCode: $exitCode,
            output: $output,
            exception: $exception,
            host: gethostname() ?: 'manual',
            command: $row->command,
        ));
    }

    /**
     * Extracts the artisan part from "/usr/bin/php artisan foo:bar arg".
     * Refuses anything that does not look like an artisan command.
     */
    private function extractArtisanCommand(?string $command, ?string $taskName): ?string
    {
        foreach ([$command, $taskName] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            if (preg_match('/\bartisan\s+(.+)$/u', $candidate, $m) === 1) {
                return trim($m[1]);
            }
        }

        return null;
    }
}
