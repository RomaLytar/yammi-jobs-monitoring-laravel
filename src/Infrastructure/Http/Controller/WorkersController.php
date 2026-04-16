<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Worker\Enum\WorkerStatus;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\WorkersViewModel;

/** @internal */
final class WorkersController extends Controller
{
    public function __invoke(Request $request, WorkerRepository $workers, ConfigRepository $config): View
    {
        return view('jobs-monitor::workers', [
            'vm' => WorkersViewModel::build(
                repository: $workers,
                silentAfterSeconds: (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120),
                expected: $this->parseExpected($config),
                now: new DateTimeImmutable,
                alivePage: max(1, (int) $request->query('page', '1')),
                silentPage: max(1, (int) $request->query('spage', '1')),
                deadPage: max(1, (int) $request->query('dpage', '1')),
                coveragePage: max(1, (int) $request->query('ppage', '1')),
            ),
        ]);
    }

    public function summary(WorkerRepository $workers, ConfigRepository $config): JsonResponse
    {
        $silentAfterSeconds = (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120);
        $now = new DateTimeImmutable;

        $alive = 0;
        $silent = 0;
        $dead = 0;
        foreach ($workers->findAll() as $worker) {
            match ($worker->classifyStatus($now, $silentAfterSeconds)) {
                WorkerStatus::Alive => $alive++,
                WorkerStatus::Silent => $silent++,
                WorkerStatus::Dead => $dead++,
            };
        }

        return new JsonResponse([
            'data' => [
                'alive' => $alive,
                'silent' => $silent,
                'dead' => $dead,
            ],
        ]);
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
