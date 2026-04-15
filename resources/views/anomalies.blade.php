@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
                <i data-lucide="trending-down" class="text-brand text-[22px]"></i>
                Duration anomalies
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Successful jobs whose duration fell wildly outside the historical p50/p95 envelope.
                Refresh baselines via <code class="px-1.5 py-0.5 rounded bg-muted">php artisan jobs-monitor:refresh-duration-baselines</code>.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wide text-muted-foreground">Short anomalies</span>
                <i data-lucide="zap" class="text-[18px] text-muted-foreground"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $vm->shortCount }}</div>
            <p class="mt-1 text-xs text-muted-foreground">Ran far faster than baseline — likely a silent no-op.</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wide text-muted-foreground">Long anomalies</span>
                <i data-lucide="hourglass" class="text-[18px] text-muted-foreground"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $vm->longCount }}</div>
            <p class="mt-1 text-xs text-muted-foreground">Ran far slower than baseline — likely stuck or degraded.</p>
        </div>
    </div>

    {{-- Baselines --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden">
        <header class="px-5 py-3 border-b border-border flex items-center justify-between">
            <h2 class="text-sm font-semibold flex items-center gap-2">
                <i data-lucide="bar-chart-2" class="text-[16px] text-brand"></i>
                Baselines per job class
            </h2>
            <span class="text-xs text-muted-foreground">{{ $vm->baselines->count() }} class(es)</span>
        </header>
        @if ($vm->baselines->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-muted-foreground">
                No baselines yet. Run <code class="px-1.5 py-0.5 rounded bg-muted">php artisan jobs-monitor:refresh-duration-baselines</code>
                after some successful jobs have been recorded.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-muted-foreground bg-muted/30">
                        <tr>
                            <th class="px-5 py-2 font-medium">Job class</th>
                            <th class="px-5 py-2 font-medium">Samples</th>
                            <th class="px-5 py-2 font-medium">p50</th>
                            <th class="px-5 py-2 font-medium">p95</th>
                            <th class="px-5 py-2 font-medium">Min</th>
                            <th class="px-5 py-2 font-medium">Max</th>
                            <th class="px-5 py-2 font-medium">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($vm->baselines as $b)
                            <tr class="hover:bg-accent/40">
                                <td class="px-5 py-3 font-mono text-xs">{{ $b->job_class }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ number_format($b->samples_count) }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ number_format($b->p50_ms) }} ms</td>
                                <td class="px-5 py-3 tabular-nums">{{ number_format($b->p95_ms) }} ms</td>
                                <td class="px-5 py-3 tabular-nums text-muted-foreground">{{ number_format($b->min_ms) }} ms</td>
                                <td class="px-5 py-3 tabular-nums text-muted-foreground">{{ number_format($b->max_ms) }} ms</td>
                                <td class="px-5 py-3 text-xs text-muted-foreground">{{ $b->computed_over_to->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Recent anomalies --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden">
        <header class="px-5 py-3 border-b border-border">
            <h2 class="text-sm font-semibold flex items-center gap-2">
                <i data-lucide="alert-triangle" class="text-[16px] text-warning"></i>
                Recent anomalies (latest 100)
            </h2>
        </header>
        @if ($vm->recentAnomalies->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-muted-foreground">
                No anomalies recorded. That's good news — your jobs are running within their normal envelope.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-muted-foreground bg-muted/30">
                        <tr>
                            <th class="px-5 py-2 font-medium">Detected</th>
                            <th class="px-5 py-2 font-medium">Job class</th>
                            <th class="px-5 py-2 font-medium">Kind</th>
                            <th class="px-5 py-2 font-medium">Duration</th>
                            <th class="px-5 py-2 font-medium">p50 / p95</th>
                            <th class="px-5 py-2 font-medium">UUID</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($vm->recentAnomalies as $a)
                            @php
                                $kindTone = $a->kind === 'short'
                                    ? 'bg-info/10 text-info border-info/20'
                                    : 'bg-warning/10 text-warning border-warning/20';
                                $kindIcon = $a->kind === 'short' ? 'zap' : 'hourglass';
                            @endphp
                            <tr class="hover:bg-accent/40">
                                <td class="px-5 py-3 text-xs text-muted-foreground tabular-nums">{{ $a->detected_at->format('Y-m-d H:i:s') }}</td>
                                <td class="px-5 py-3 font-mono text-xs">{{ $a->job_class }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium border {{ $kindTone }}">
                                        <i data-lucide="{{ $kindIcon }}" class="text-[12px]"></i>
                                        {{ ucfirst($a->kind) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 tabular-nums font-medium">{{ number_format($a->duration_ms) }} ms</td>
                                <td class="px-5 py-3 text-xs text-muted-foreground tabular-nums">
                                    {{ number_format($a->baseline_p50_ms) }} / {{ number_format($a->baseline_p95_ms) }} ms
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-muted-foreground truncate max-w-[12rem]">{{ $a->job_uuid }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
