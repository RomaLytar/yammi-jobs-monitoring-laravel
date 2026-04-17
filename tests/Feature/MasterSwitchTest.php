<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Yammi\JobsMonitor\Tests\TestCase;

final class MasterSwitchTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('jobs-monitor.enabled', false);
        $app['config']->set('jobs-monitor.api.enabled', true);
    }

    public function test_dashboard_routes_are_not_registered_when_disabled(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $names = $this->routeNames($router);

        $offending = array_values(array_filter(
            $names,
            static fn (string $name): bool => str_starts_with($name, 'jobs-monitor.'),
        ));

        self::assertSame([], $offending, 'no jobs-monitor routes should exist when master switch is off');
    }

    public function test_scheduled_commands_are_not_registered_when_disabled(): void
    {
        /** @var Application $app */
        $app = $this->app;
        $app->boot();

        /** @var Schedule $schedule */
        $schedule = $app->make(Schedule::class);

        $matching = array_values(array_filter(array_map(
            static fn ($event): string => (string) ($event->description ?? ''),
            $schedule->events(),
        ), static fn (string $desc): bool => str_contains($desc, 'jobs-monitor:')));

        self::assertSame([], $matching);
    }

    /**
     * @return list<string>
     */
    private function routeNames(Router $router): array
    {
        $names = [];
        foreach ($router->getRoutes()->getRoutesByName() as $name => $_route) {
            $names[] = (string) $name;
        }

        return $names;
    }
}
