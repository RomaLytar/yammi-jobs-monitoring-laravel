@php
    /** @var \Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\DurationAnomalyModel $anomaly */
    /** @var \Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobRecordModel|null $job */

    $deltaText = function () use ($anomaly): string {
        $reference = $anomaly->kind === 'short' ? $anomaly->baseline_p50_ms : $anomaly->baseline_p95_ms;
        if ($reference <= 0) {
            return '—';
        }
        $ratio = $anomaly->duration_ms / $reference;
        if ($anomaly->kind === 'short') {
            return sprintf('%.1f× faster than p50 (%dms / %dms)', 1 / max(0.0001, $ratio), $reference, $anomaly->duration_ms);
        }
        return sprintf('%.1f× slower than p95 (%dms / %dms)', $ratio, $reference, $anomaly->duration_ms);
    };
@endphp

{{-- Anomaly summary --}}
<div class="rounded-lg border border-border bg-card/50 p-3 mb-3">
    <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
        <i data-lucide="trending-down" class="text-[12px] text-brand"></i>
        Why this is flagged
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
        <div>
            <span class="text-[10px] font-medium text-muted-foreground uppercase">Kind</span>
            @if ($anomaly->kind === 'short')
                <p class="text-info font-medium flex items-center gap-1.5"><i data-lucide="zap" class="text-[14px]"></i> Short — likely silent no-op</p>
            @else
                <p class="text-warning font-medium flex items-center gap-1.5"><i data-lucide="hourglass" class="text-[14px]"></i> Long — likely stuck/degraded</p>
            @endif
        </div>
        <div>
            <span class="text-[10px] font-medium text-muted-foreground uppercase">Deviation</span>
            <p class="text-sm">{{ $deltaText() }}</p>
        </div>
        <div>
            <span class="text-[10px] font-medium text-muted-foreground uppercase">Baseline</span>
            <p class="text-sm tabular-nums">p50 {{ number_format($anomaly->baseline_p50_ms) }} ms · p95 {{ number_format($anomaly->baseline_p95_ms) }} ms · n={{ number_format($anomaly->samples_count) }}</p>
        </div>
    </div>
</div>

@if ($job === null)
    <div class="rounded-lg border border-warning/30 bg-warning/10 text-warning text-xs px-3 py-2 mb-3 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="text-[14px]"></i>
        The original job record was pruned (retention policy). Only the anomaly snapshot above is available.
    </div>
@else
    {{-- Job header info --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
        @foreach ([
            ['Job class', $job->job_class, true],
            ['UUID', $job->uuid, true],
            ['Attempt', (string) $job->attempt, false],
            ['Status', $job->status, false],
            ['Connection', $job->connection, false],
            ['Queue', $job->queue, false],
            ['Started at', optional($job->started_at)->format('Y-m-d H:i:s.v'), false],
            ['Finished at', optional($job->finished_at)->format('Y-m-d H:i:s.v') ?? '—', false],
        ] as [$label, $val, $mono])
            <div>
                <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">{{ $label }}</span>
                <p class="text-sm {{ $mono ? 'font-mono break-all' : '' }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    {{-- Outcome (if reported) --}}
    @if ($job->outcome_status !== null || $job->outcome_processed !== null)
        <div class="rounded-lg border border-border bg-card/50 p-3 mb-3">
            <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <i data-lucide="package-check" class="text-[12px] text-success"></i>
                Outcome (from ReportsOutcome)
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm tabular-nums">
                <div><span class="text-[10px] text-muted-foreground uppercase">Status</span><p>{{ $job->outcome_status ?? '—' }}</p></div>
                <div><span class="text-[10px] text-muted-foreground uppercase">Processed</span><p>{{ $job->outcome_processed ?? '—' }}</p></div>
                <div><span class="text-[10px] text-muted-foreground uppercase">Skipped</span><p>{{ $job->outcome_skipped ?? '—' }}</p></div>
                <div><span class="text-[10px] text-muted-foreground uppercase">Warnings</span><p>{{ $job->outcome_warnings_count ?? '—' }}</p></div>
            </div>
        </div>
    @endif

    {{-- Progress (if reported) --}}
    @if ($job->progress_current !== null)
        <div class="rounded-lg border border-border bg-card/50 p-3 mb-3">
            <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <i data-lucide="activity" class="text-[12px] text-info"></i>
                Progress (from ReportsProgress)
            </p>
            <p class="text-sm tabular-nums">
                {{ number_format($job->progress_current) }}@if ($job->progress_total !== null) / {{ number_format($job->progress_total) }}@endif
                @if ($job->progress_description) — {{ $job->progress_description }} @endif
                @if ($job->progress_updated_at)
                    <span class="text-xs text-muted-foreground ml-2">last update {{ $job->progress_updated_at->format('Y-m-d H:i:s') }}</span>
                @endif
            </p>
        </div>
    @endif

    {{-- Payload --}}
    @if (! empty($job->payload))
        <div class="mb-3">
            <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Payload</span>
            <pre class="mt-1 bg-card border border-border rounded-lg p-3 text-xs overflow-x-auto whitespace-pre-wrap break-words font-mono max-h-80">{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @else
        <div class="mb-3 rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground flex items-center gap-2">
            <i data-lucide="info" class="text-[14px]"></i>
            No payload stored. Set <code class="px-1 py-0.5 rounded bg-muted">JOBS_MONITOR_STORE_PAYLOAD=true</code> to capture it on future runs.
        </div>
    @endif

    {{-- Exception --}}
    @if (! empty($job->exception))
        <div class="mb-3">
            <span class="text-[10px] font-medium text-destructive uppercase tracking-wider">Exception</span>
            <pre class="mt-1 bg-destructive/10 border border-destructive/20 rounded-lg p-3 text-xs text-destructive overflow-x-auto whitespace-pre-wrap break-words font-mono max-h-80">{{ $job->exception }}</pre>
        </div>
    @endif

    <div class="flex justify-end">
        <a href="{{ route('jobs-monitor.detail', ['uuid' => $job->uuid, 'attempt' => $job->attempt]) }}"
           class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors shadow-xs">
            View full job detail &amp; retry timeline
            <i data-lucide="arrow-right" class="text-[13px]"></i>
        </a>
    </div>
@endif
