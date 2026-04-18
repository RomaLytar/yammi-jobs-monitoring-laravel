<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http;

use DateTimeImmutable;
use Yammi\JobsMonitor\Application\Action\RecordFailureFingerprintAction;
use Yammi\JobsMonitor\Tests\TestCase;

final class FailureGroupsPageTest extends TestCase
{
    public function test_page_renders_with_empty_state(): void
    {
        $response = $this->get('/jobs-monitor/failures');

        $response->assertStatus(200);
        $response->assertSee('Failure groups', false);
        $response->assertSee('No failure groups yet', false);
    }

    public function test_page_renders_groups_with_bulk_and_kebab_actions(): void
    {
        $action = $this->app->make(RecordFailureFingerprintAction::class);
        $fp = $action(
            id: '550e8400-e29b-41d4-a716-446655440001',
            attempt: 1,
            jobClass: 'App\\Jobs\\OrderImportJob',
            exception: new \RuntimeException('Boom'),
            occurredAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        );

        $response = $this->get('/jobs-monitor/failures');

        $response->assertStatus(200);
        $response->assertSee($fp->hash, false);
        $response->assertSee('data-jm-bulk-scope="failures"', false);
        $response->assertSee('data-jm-bulk-row', false);
        $response->assertSee('data-fg-menu', false);
        $response->assertSee('Retry all groups', false);
        $response->assertSee('Retry group', false);
        $response->assertSee('Delete group', false);
    }
}
