<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\WorkersViewModel;

/** @internal */
final class WorkersController extends Controller
{
    public function __invoke(Request $request, WorkerRepository $workers, ConfigRepository $config): View
    {
        /** @var array<mixed, mixed> $rawExpected */
        $rawExpected = (array) $config->get('jobs-monitor.workers.expected', []);

        $expected = [];
        foreach ($rawExpected as $key => $value) {
            if (is_string($key) && $key !== '') {
                $expected[$key] = max(0, (int) $value);
            }
        }

        return view('jobs-monitor::workers', [
            'vm' => WorkersViewModel::build(
                repository: $workers,
                silentAfterSeconds: (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120),
                expected: $expected,
                now: new DateTimeImmutable,
                alivePage: max(1, (int) $request->query('page', '1')),
                silentPage: max(1, (int) $request->query('spage', '1')),
                coveragePage: max(1, (int) $request->query('ppage', '1')),
            ),
        ]);
    }
}
