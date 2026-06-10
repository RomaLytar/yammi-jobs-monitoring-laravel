<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Action;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\PruneMonitorDataAction;
use Yammi\JobsMonitor\Application\Action\PruneTarget;
use Yammi\JobsMonitor\Application\Contract\ConfigReader;

final class PruneMonitorDataActionTest extends TestCase
{
    public function test_prunes_each_target_with_its_own_retention_and_returns_counts(): void
    {
        $cutoffs = [];
        $config = $this->config([
            'jobs-monitor.retention_days' => 180,
            'jobs-monitor.workers.retention_days' => 30,
        ]);

        $action = new PruneMonitorDataAction($config, [
            new PruneTarget('jobs', 'jobs-monitor.retention_days', 180, function (DateTimeImmutable $c) use (&$cutoffs): int {
                $cutoffs['jobs'] = $c;

                return 5;
            }),
            new PruneTarget('worker_heartbeats', 'jobs-monitor.workers.retention_days', 30, function (DateTimeImmutable $c) use (&$cutoffs): int {
                $cutoffs['worker_heartbeats'] = $c;

                return 2;
            }, overridableByDays: false),
        ]);

        $result = $action();

        self::assertSame(['jobs' => 5, 'worker_heartbeats' => 2], $result->deletedByDataset);
        self::assertSame(7, $result->total());
        // jobs cutoff ≈ now - 180d, heartbeats ≈ now - 30d
        self::assertSame(180, $this->daysAgo($cutoffs['jobs']));
        self::assertSame(30, $this->daysAgo($cutoffs['worker_heartbeats']));
    }

    public function test_days_override_applies_only_to_main_targets(): void
    {
        $cutoffs = [];
        $config = $this->config([
            'jobs-monitor.retention_days' => 180,
            'jobs-monitor.workers.retention_days' => 30,
        ]);

        $action = new PruneMonitorDataAction($config, [
            new PruneTarget('jobs', 'jobs-monitor.retention_days', 180, function (DateTimeImmutable $c) use (&$cutoffs): int {
                $cutoffs['jobs'] = $c;

                return 0;
            }),
            new PruneTarget('worker_heartbeats', 'jobs-monitor.workers.retention_days', 30, function (DateTimeImmutable $c) use (&$cutoffs): int {
                $cutoffs['worker_heartbeats'] = $c;

                return 0;
            }, overridableByDays: false),
        ]);

        $action(7);

        self::assertSame(7, $this->daysAgo($cutoffs['jobs']), 'main target honours --days');
        self::assertSame(30, $this->daysAgo($cutoffs['worker_heartbeats']), 'heartbeats keep their own retention');
    }

    public function test_falls_back_to_default_when_config_value_is_not_numeric(): void
    {
        $cutoffs = [];
        $config = $this->config(['jobs-monitor.retention_days' => 'oops']);

        $action = new PruneMonitorDataAction($config, [
            new PruneTarget('jobs', 'jobs-monitor.retention_days', 180, function (DateTimeImmutable $c) use (&$cutoffs): int {
                $cutoffs['jobs'] = $c;

                return 0;
            }),
        ]);

        $action();

        self::assertSame(180, $this->daysAgo($cutoffs['jobs']));
    }

    private function daysAgo(DateTimeImmutable $cutoff): int
    {
        $now = new DateTimeImmutable('now');

        return (int) round(($now->getTimestamp() - $cutoff->getTimestamp()) / 86400);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function config(array $values): ConfigReader
    {
        return new class($values) implements ConfigReader
        {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values) {}

            public function get(string $path, mixed $default = null): mixed
            {
                return $this->values[$path] ?? $default;
            }
        };
    }
}
