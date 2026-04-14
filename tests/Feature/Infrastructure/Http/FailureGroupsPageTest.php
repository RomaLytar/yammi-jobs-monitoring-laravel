<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use Yammi\JobsMonitor\Tests\TestCase;

final class FailureGroupsPageTest extends TestCase
{
    public function test_page_renders_and_wires_to_json_endpoint(): void
    {
        $response = $this->get('/jobs-monitor/failures');

        $response->assertStatus(200);
        $response->assertSee('Failure groups', false);
        $response->assertSee('data-jm-groups-body', false);
        $response->assertSee('jobs-monitor\\/failures\\/groups', false);
    }
}
