<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Settings;

use Yammi\JobsMonitor\Tests\TestCase;

final class PlaygroundControllerTest extends TestCase
{
    public function test_index_renders_with_facade_catalog_and_group_headers(): void
    {
        $response = $this->get('/jobs-monitor/settings/playground');

        $response->assertOk();
        $response->assertSee('Facade Playground');
        $response->assertSee('YammiJobs');
        $response->assertSee('YammiJobsManage');
        $response->assertSee('YammiJobsSettings');
        $response->assertSee('Read-only queries', false);
        $response->assertSee('Mutations:', false);
        $response->assertSee('Settings CRUD', false);
    }

    public function test_index_lists_catalogued_methods(): void
    {
        $response = $this->get('/jobs-monitor/settings/playground');

        $response->assertOk();
        $response->assertSee('data-key="YammiJobs::failed"', false);
        $response->assertSee('data-key="YammiJobs::dlq"', false);
        $response->assertSee('data-key="YammiJobsManage::retryDlq"', false);
        $response->assertSee('data-key="YammiJobsSettings::alerts"', false);
    }

    public function test_index_appears_on_settings_index(): void
    {
        $response = $this->get('/jobs-monitor/settings');

        $response->assertOk();
        $response->assertSee('Facade Playground');
    }

    public function test_execute_runs_read_method_and_returns_shaped_result(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobs::countFailures',
            'args' => ['period' => 'all'],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['method', 'result']);
        $this->assertSame('YammiJobs::countFailures', $response->json('method'));
        $this->assertIsInt($response->json('result'));
    }

    public function test_execute_rejects_method_not_in_catalog(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobs::hackTheGibson',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Unknown method.',
            'error_class' => 'UnknownMethod',
        ]);
    }

    public function test_execute_rejects_malformed_method_string_with_shaped_error(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => '<script>alert(1)</script>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error', 'error_class']);
        $this->assertSame('InvalidPlaygroundRequest', $response->json('error_class'));
    }

    public function test_execute_returns_typed_error_on_invalid_period(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobs::failed',
            'args' => ['period' => 'balalaika'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_class' => 'InvalidPeriod']);
    }

    public function test_execute_returns_typed_error_on_invalid_uuid(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobs::attempts',
            'args' => ['uuid' => 'not-a-uuid'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_class' => 'InvalidPlaygroundArgument']);
    }

    public function test_execute_returns_typed_error_on_bad_pagination(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobs::jobs',
            'args' => ['perPage' => 10000],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_class' => 'InvalidPagination']);
    }

    public function test_execute_forbids_destructive_when_not_authenticated(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobsManage::deleteDlq',
            'args' => ['uuid' => '550e8400-e29b-41d4-a716-446655440001'],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error_class' => 'PlaygroundForbidden']);
    }

    public function test_execute_missing_required_argument_returns_shaped_error(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobs::stats',
            'args' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_class' => 'InvalidPlaygroundArgument']);
    }

    public function test_dates_in_result_use_readable_format_not_iso(): void
    {
        $response = $this->postJson('/jobs-monitor/settings/playground/execute', [
            'method' => 'YammiJobs::scheduled',
            'args' => ['page' => 1, 'perPage' => 50],
        ]);

        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('T00:00:00', $body);
        $this->assertStringNotContainsString('+00:00', $body);
    }
}
