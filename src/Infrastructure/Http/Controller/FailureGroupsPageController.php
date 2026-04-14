<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/** @internal */
final class FailureGroupsPageController extends Controller
{
    public function __invoke(): View
    {
        return view('jobs-monitor::failure-groups');
    }
}
