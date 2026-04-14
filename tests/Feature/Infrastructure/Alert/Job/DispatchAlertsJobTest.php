<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Alert\Job;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Yammi\JobsMonitor\Infrastructure\Alert\Job\DispatchAlertsJob;
use Yammi\JobsMonitor\Tests\TestCase;

final class DispatchAlertsJobTest extends TestCase
{
    public function test_is_a_queued_job(): void
    {
        self::assertTrue(is_a(DispatchAlertsJob::class, ShouldQueue::class, true));
    }

    public function test_dispatches_onto_the_queue(): void
    {
        Queue::fake();

        DispatchAlertsJob::dispatch();

        Queue::assertPushed(DispatchAlertsJob::class);
    }
}
