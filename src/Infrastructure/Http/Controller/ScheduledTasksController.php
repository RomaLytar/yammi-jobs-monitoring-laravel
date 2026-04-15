<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\ScheduledTasksViewModel;

/** @internal */
final class ScheduledTasksController extends Controller
{
    public function __construct(
        private readonly ScheduledTaskRunRepository $repository,
    ) {}

    public function __invoke(): View
    {
        return view('jobs-monitor::scheduled-tasks', [
            'vm' => ScheduledTasksViewModel::fromRepository($this->repository),
        ]);
    }
}
