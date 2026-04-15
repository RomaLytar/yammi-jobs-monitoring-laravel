<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Job\Concern;

use Throwable;
use Yammi\JobsMonitor\Application\Action\RecordJobProgressAction;

/**
 * Drop-in trait for queue jobs that want to emit progress ticks as they
 * work. Each progress() call persists the current/total pair so the
 * dashboard and the partial-completion alert can tell where a long job
 * stopped if it later fails mid-flight.
 *
 * Usage:
 *
 *     class ProcessImport implements ShouldQueue
 *     {
 *         use ReportsProgress;
 *
 *         public function handle(): void
 *         {
 *             $total = $this->rows->count();
 *             foreach ($this->rows as $i => $row) {
 *                 $this->processRow($row);
 *                 $this->progress($i + 1, $total);
 *             }
 *         }
 *     }
 *
 * Progress recording is best-effort: if the monitor is unreachable or
 * disabled, the trait silently skips. The host job's handle() is never
 * broken by observability.
 */
trait ReportsProgress
{
    protected function progress(int $current, ?int $total = null, ?string $description = null): void
    {
        try {
            if (! property_exists($this, 'job') || $this->job === null) {
                return;
            }

            $uuid = $this->job->uuid();
            if (! is_string($uuid) || $uuid === '') {
                return;
            }

            /** @var RecordJobProgressAction $action */
            $action = app(RecordJobProgressAction::class);
            $action(
                uuid: $uuid,
                attempt: $this->job->attempts(),
                current: $current,
                total: $total,
                description: $description,
            );
        } catch (Throwable $e) {
            // Never break the host's job over observability.
        }
    }
}
