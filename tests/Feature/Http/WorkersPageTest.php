<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Http;

use DateTimeImmutable;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerHeartbeat;
use Yammi\JobsMonitor\Domain\Worker\ValueObject\WorkerIdentifier;
use Yammi\JobsMonitor\Tests\TestCase;

final class WorkersPageTest extends TestCase
{
    public function test_empty_fleet_renders_cards_with_zero_counts(): void
    {
        $this->get('/jobs-monitor/workers')
            ->assertOk()
            ->assertSeeText('Workers')
            ->assertSeeText('Alive')
            ->assertSeeText('Silent')
            ->assertSeeText('Dead')
            ->assertSeeText('Coverage');
    }

    public function test_alive_and_silent_workers_split_into_their_respective_blocks(): void
    {
        /** @var WorkerRepository $repo */
        $repo = $this->app->make(WorkerRepository::class);
        $this->app['config']->set('jobs-monitor.workers.silent_after_seconds', 60);

        $repo->recordHeartbeat(new WorkerHeartbeat(
            new WorkerIdentifier('alive:1'), 'redis', 'default', 'host-a', 1, new DateTimeImmutable,
        ));
        $repo->recordHeartbeat(new WorkerHeartbeat(
            new WorkerIdentifier('silent:1'), 'redis', 'emails', 'host-b', 2,
            (new DateTimeImmutable)->modify('-5 minutes'),
        ));

        $response = $this->get('/jobs-monitor/workers')->assertOk();
        $body = (string) $response->getContent();

        self::assertStringContainsString('alive:1', $body);
        self::assertStringContainsString('silent:1', $body);
        // Silent table header exists and is below the alive table.
        self::assertLessThan(
            strpos($body, 'silent:1'),
            strpos($body, 'alive:1'),
        );
    }

    public function test_coverage_block_shows_expected_vs_observed(): void
    {
        $this->app['config']->set('jobs-monitor.workers.silent_after_seconds', 60);
        $this->app['config']->set('jobs-monitor.workers.expected', [
            'redis:default' => 2,
            'redis:emails' => 1,
        ]);

        /** @var WorkerRepository $repo */
        $repo = $this->app->make(WorkerRepository::class);
        $repo->recordHeartbeat(new WorkerHeartbeat(
            new WorkerIdentifier('a:1'), 'redis', 'default', 'host-a', 1, new DateTimeImmutable,
        ));

        $response = $this->get('/jobs-monitor/workers')->assertOk();
        $body = (string) $response->getContent();

        self::assertStringContainsString('redis:default', $body);
        self::assertStringContainsString('redis:emails', $body);
        self::assertStringContainsString('DEGRADED', $body);
        self::assertStringContainsString('DOWN', $body);
    }

    public function test_secondary_block_pagination_preserves_primary_page(): void
    {
        /** @var WorkerRepository $repo */
        $repo = $this->app->make(WorkerRepository::class);
        $this->app['config']->set('jobs-monitor.workers.silent_after_seconds', 60);

        // 30 silent workers → spage=2 will be needed.
        for ($i = 1; $i <= 30; $i++) {
            $repo->recordHeartbeat(new WorkerHeartbeat(
                new WorkerIdentifier('silent:'.$i),
                'redis',
                'default',
                'host-'.$i,
                $i,
                (new DateTimeImmutable)->modify('-5 minutes'),
            ));
        }

        $response = $this->get('/jobs-monitor/workers?page=2&spage=1')->assertOk();
        $body = (string) $response->getContent();

        // Silent block's pagination must carry page=2 alongside spage links.
        self::assertStringContainsString('page=2', $body);
        self::assertStringContainsString('spage=2', $body);
    }
}
