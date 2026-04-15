@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
                <i data-lucide="calendar-clock" class="text-brand text-[22px]"></i>
                Scheduled tasks
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Every Laravel scheduler run captured by the monitor. Late = stayed in Running past tolerance.
            </p>
        </div>
    </div>

    {{-- Status counters --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @php
            $statusTone = [
                'success' => ['badge' => 'bg-success/10 text-success border-success/20', 'icon' => 'check-circle-2'],
                'failed'  => ['badge' => 'bg-destructive/10 text-destructive border-destructive/20', 'icon' => 'x-circle'],
                'late'    => ['badge' => 'bg-warning/10 text-warning border-warning/20', 'icon' => 'alarm-clock'],
                'running' => ['badge' => 'bg-info/10 text-info border-info/20', 'icon' => 'loader'],
                'skipped' => ['badge' => 'bg-muted text-muted-foreground border-border', 'icon' => 'minus-circle'],
            ];
        @endphp
        @foreach ($vm->statusCounts as $status => $count)
            @php $tone = $statusTone[$status] ?? $statusTone['skipped']; @endphp
            <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wide text-muted-foreground">{{ ucfirst($status) }}</span>
                    <i data-lucide="{{ $tone['icon'] }}" class="text-[18px] text-muted-foreground"></i>
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $count }}</div>
            </div>
        @endforeach
    </div>

    {{-- Per-task summary (latest run per mutex) --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden">
        <header class="px-5 py-3 border-b border-border flex items-center justify-between">
            <h2 class="text-sm font-semibold flex items-center gap-2">
                <i data-lucide="list" class="text-[16px] text-brand"></i>
                Tasks (latest run per task)
            </h2>
            <span class="text-xs text-muted-foreground">{{ count($vm->latestPerMutex) }} task(s)</span>
        </header>
        @if (count($vm->latestPerMutex) === 0)
            <div class="px-5 py-12 text-center text-sm text-muted-foreground">
                No scheduled-task runs recorded yet. Run <code class="px-1.5 py-0.5 rounded bg-muted">php artisan schedule:run</code> to populate.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-muted-foreground bg-muted/30">
                        <tr>
                            <th class="px-5 py-2 font-medium">Task</th>
                            <th class="px-5 py-2 font-medium">Cron</th>
                            <th class="px-5 py-2 font-medium">Status</th>
                            <th class="px-5 py-2 font-medium">Started</th>
                            <th class="px-5 py-2 font-medium">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($vm->latestPerMutex as $run)
                            @php $tone = $statusTone[$run->status()->value] ?? $statusTone['skipped']; @endphp
                            <tr class="hover:bg-accent/40">
                                <td class="px-5 py-3 font-medium truncate max-w-xs" title="{{ $run->taskName }}">{{ $run->taskName }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-muted-foreground">{{ $run->expression }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium border {{ $tone['badge'] }}">
                                        <i data-lucide="{{ $tone['icon'] }}" class="text-[12px]"></i>
                                        {{ $run->status()->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-muted-foreground tabular-nums">{{ $run->startedAt->format('Y-m-d H:i:s') }}</td>
                                <td class="px-5 py-3 text-xs tabular-nums">
                                    @if ($run->duration() !== null)
                                        {{ number_format($run->duration()->milliseconds) }} ms
                                    @else
                                        <span class="text-muted-foreground">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Recent runs across all tasks --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden">
        <header class="px-5 py-3 border-b border-border">
            <h2 class="text-sm font-semibold flex items-center gap-2">
                <i data-lucide="history" class="text-[16px] text-brand"></i>
                Recent runs (latest 50)
            </h2>
        </header>
        @if (count($vm->recentRuns) === 0)
            <div class="px-5 py-12 text-center text-sm text-muted-foreground">No runs yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-muted-foreground bg-muted/30">
                        <tr>
                            <th class="px-5 py-2 font-medium">Task</th>
                            <th class="px-5 py-2 font-medium">Status</th>
                            <th class="px-5 py-2 font-medium">Started</th>
                            <th class="px-5 py-2 font-medium">Duration</th>
                            <th class="px-5 py-2 font-medium">Exception</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($vm->recentRuns as $run)
                            @php $tone = $statusTone[$run->status()->value] ?? $statusTone['skipped']; @endphp
                            <tr class="hover:bg-accent/40">
                                <td class="px-5 py-3 truncate max-w-xs" title="{{ $run->taskName }}">{{ $run->taskName }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium border {{ $tone['badge'] }}">
                                        <i data-lucide="{{ $tone['icon'] }}" class="text-[12px]"></i>
                                        {{ $run->status()->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-muted-foreground tabular-nums">{{ $run->startedAt->format('Y-m-d H:i:s') }}</td>
                                <td class="px-5 py-3 text-xs tabular-nums">
                                    @if ($run->duration() !== null)
                                        {{ number_format($run->duration()->milliseconds) }} ms
                                    @else
                                        <span class="text-muted-foreground">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-destructive truncate max-w-md" title="{{ $run->exception() }}">{{ $run->exception() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
