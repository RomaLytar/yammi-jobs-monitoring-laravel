<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Failure\Repository\FailureGroupRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\FailureGroupsViewModel;

/** @internal */
final class FailureGroupsPageController extends Controller
{
    public function __construct(
        private readonly FailureGroupRepository $groups,
    ) {}

    public function __invoke(Request $request): View
    {
        $page = max(1, (int) $request->query('page', '1'));
        $vm = FailureGroupsViewModel::fromRepository($this->groups, $page);

        return view('jobs-monitor::failure-groups', ['vm' => $vm]);
    }
}
