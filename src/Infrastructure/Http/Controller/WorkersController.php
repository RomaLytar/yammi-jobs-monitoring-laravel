<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\WorkersViewModel;

/** @internal */
final class WorkersController extends Controller
{
    public function __invoke(Request $request, WorkerRepository $workers, ConfigRepository $config): View
    {
        return view('jobs-monitor::workers', [
            'vm' => $this->buildVm($request, $workers, $config),
        ]);
    }

    /**
     * Returns only the inner content partial (no layout) so the JS
     * auto-refresh can swap the entire block without a full page load.
     */
    public function summary(Request $request, WorkerRepository $workers, ConfigRepository $config): Response
    {
        $html = view('jobs-monitor::partials.workers-content', [
            'vm' => $this->buildVm($request, $workers, $config),
        ])->render();

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function buildVm(
        Request $request,
        WorkerRepository $workers,
        ConfigRepository $config,
    ): WorkersViewModel {
        return WorkersViewModel::build(
            repository: $workers,
            silentAfterSeconds: (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120),
            expected: $this->parseExpected($config),
            now: new DateTimeImmutable,
            alivePage: max(1, (int) $request->query('page', '1')),
            silentPage: max(1, (int) $request->query('spage', '1')),
            deadPage: max(1, (int) $request->query('dpage', '1')),
            coveragePage: max(1, (int) $request->query('ppage', '1')),
        );
    }

    /**
     * @return array<string, int>
     */
    private function parseExpected(ConfigRepository $config): array
    {
        /** @var array<mixed, mixed> $raw */
        $raw = (array) $config->get('jobs-monitor.workers.expected', []);

        $expected = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && $key !== '') {
                $expected[$key] = max(0, (int) $value);
            }
        }

        return $expected;
    }
}
