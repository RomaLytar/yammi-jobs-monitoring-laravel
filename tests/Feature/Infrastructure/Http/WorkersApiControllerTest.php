<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Tests\TestCase;

final class WorkersApiControllerTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function enableApi($app): void
    {
        $app['config']->set('jobs-monitor.api.enabled', true);
        $app['config']->set('jobs-monitor.workers.silent_after_seconds', 60);
    }

    /**
     * @define-env enableApi
     */
    public function test_index_lists_workers_with_status(): void
    {
        $this->seedWorkers();

        $response = $this->getJson('/api/jobs-monitor/workers');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [[
                'worker_id', 'connection', 'queue', 'queue_key', 'host',
                'pid', 'last_seen_at', 'stopped_at', 'status',
            ]],
            'meta' => ['total', 'page', 'per_page', 'last_page', 'silent_after_seconds'],
        ]);

        self::assertSame(3, $response->json('meta.total'));
        self::assertSame(60, $response->json('meta.silent_after_seconds'));
    }

    /**
     * @define-env enableApi
     */
    public function test_index_filters_by_status(): void
    {
        $this->seedWorkers();

        $response = $this->getJson('/api/jobs-monitor/workers?status=silent');

        $response->assertOk();
        self::assertSame(1, $response->json('meta.total'));
        self::assertSame('silent', $response->json('data.0.status'));
        self::assertSame('silent:1', $response->json('data.0.worker_id'));
    }

    public function test_index_returns_404_when_api_disabled(): void
    {
        // No @define-env enableApi here — the default is api.enabled=false
        // so the route is never registered and the URL 404s.
        $this->getJson('/api/jobs-monitor/workers')->assertNotFound();
    }

    /**
     * @define-env enableApi
     */
    public function test_status_counts_returns_totals_and_coverage(): void
    {
        $this->app['config']->set('jobs-monitor.workers.expected', [
            'redis:default' => 2,
            'redis:emails' => 1,
        ]);

        $this->seedWorkers();

        $response = $this->getJson('/api/jobs-monitor/workers/status-counts');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'alive', 'silent', 'dead', 'coverage' => [['queue_key', 'observed', 'expected', 'status']],
            ],
            'meta' => ['silent_after_seconds'],
        ]);

        self::assertSame(1, $response->json('data.alive'));
        self::assertSame(1, $response->json('data.silent'));
        self::assertSame(1, $response->json('data.dead'));

        $coverage = collect($response->json('data.coverage'))->keyBy('queue_key');
        self::assertSame('degraded', $coverage['redis:default']['status']);
        self::assertSame(1, $coverage['redis:default']['observed']);
        self::assertSame('down', $coverage['redis:emails']['status']);
    }

    private function seedWorkers(): void
    {
        /** @var WorkerRepository $repo */
        $repo = $this->app->make(WorkerRepository::class);

        $repo->recordHeartbeat(new WorkerHeartbeat(
            new WorkerIdentifier('alive:1'), 'redis', 'default', 'host-a', 1, new DateTimeImmutable,
        ));
        $repo->recordHeartbeat(new WorkerHeartbeat(
            new WorkerIdentifier('silent:1'), 'redis', 'default', 'host-b', 2,
            (new DateTimeImmutable)->modify('-5 minutes'),
        ));
        $repo->recordHeartbeat(new WorkerHeartbeat(
            new WorkerIdentifier('dead:1'), 'redis', 'emails', 'host-c', 3,
            (new DateTimeImmutable)->modify('-2 hours'),
        ));
    }
}
