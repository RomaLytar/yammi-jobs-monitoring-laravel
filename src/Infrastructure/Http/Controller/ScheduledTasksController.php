<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\ScheduledTasksViewModel;

/** @internal */
final class ScheduledTasksController extends Controller
{
    public function __construct(
        private readonly ScheduledTaskRunRepository $repository,
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
}
