@extends('jobs-monitor::layouts.app')

@php
    // Block-scoped base params so each independent table keeps its own
    // page counter when another block paginates.
    // page = alive, spage = silent, dpage = dead, ppage = coverage.
    $aliveExtraParams = [
        'spage' => $vm->silentPage,
        'dpage' => $vm->deadPage,
        'ppage' => $vm->coveragePage,
    ];
    $silentExtraParams = [
        'page' => $vm->alivePage,
        'dpage' => $vm->deadPage,
        'ppage' => $vm->coveragePage,
    ];
    $deadExtraParams = [
        'page' => $vm->alivePage,
        'spage' => $vm->silentPage,
        'ppage' => $vm->coveragePage,
    ];
    $coverageExtraParams = [
        'page' => $vm->alivePage,
        'spage' => $vm->silentPage,
        'dpage' => $vm->deadPage,
    ];

    $statusBadges = [
        'alive' => 'bg-success/10 text-success border-success/20',
        'silent' => 'bg-warning/10 text-warning border-warning/20',
        'dead' => 'bg-destructive/10 text-destructive border-destructive/20',
    ];

    $coverageBadges = [
        'ok' => 'bg-success/10 text-success border-success/20',
        'degraded' => 'bg-warning/10 text-warning border-warning/20',
        'down' => 'bg-destructive/10 text-destructive border-destructive/20',
    ];

    $formatAge = static function (\DateTimeImmutable $seen, \DateTimeImmutable $now): string {
        $elapsed = max(0, $now->getTimestamp() - $seen->getTimestamp());
        if ($elapsed < 60) {
            return $elapsed.'s ago';
        }
        if ($elapsed < 3600) {
            return (int) floor($elapsed / 60).'m ago';
        }
        if ($elapsed < 86400) {
            return (int) floor($elapsed / 3600).'h ago';
        }

        return (int) floor($elapsed / 86400).'d ago';
    };
@endphp

@section('content')
<div class="space-y-6" id="workers-top">
    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
                <i data-lucide="cpu" class="text-brand text-[22px]"></i>
                Workers
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Live heartbeat view. Silent after {{ $vm->silentAfterSeconds }}s of no pulse. Counters refresh every 5 s.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wide text-muted-foreground">Alive</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-success/10 text-success"><i data-lucide="heart-pulse" class="text-[14px]"></i></span>
            </div>
            <div data-workers-card="alive" class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $vm->aliveTotal > 0 ? 'text-success' : 'text-foreground' }}">{{ number_format($vm->aliveTotal) }}</div>
            <p class="mt-1 text-xs text-muted-foreground">Heartbeat received within the threshold.</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wide text-muted-foreground">Silent</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-warning/10 text-warning"><i data-lucide="bell-off" class="text-[14px]"></i></span>
            </div>
            <div data-workers-card="silent" class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $vm->silentTotal > 0 ? 'text-warning' : 'text-foreground' }}">{{ number_format($vm->silentTotal) }}</div>
            <p class="mt-1 text-xs text-muted-foreground">Last heartbeat older than the threshold.</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wide text-muted-foreground">Dead</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-destructive/10 text-destructive"><i data-lucide="skull" class="text-[14px]"></i></span>
            </div>
            <div data-workers-card="dead" class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $vm->deadTotal > 0 ? 'text-destructive' : 'text-foreground' }}">{{ number_format($vm->deadTotal) }}</div>
            <p class="mt-1 text-xs text-muted-foreground">Stopped or offline far past the threshold.</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wide text-muted-foreground">Coverage</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-info/10 text-info"><i data-lucide="layers" class="text-[14px]"></i></span>
            </div>
            <div class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $vm->coverageTotal > 0 ? 'text-info' : 'text-foreground' }}">{{ number_format($vm->coverageTotal) }}</div>
            <p class="mt-1 text-xs text-muted-foreground">Queues with configured expectations.</p>
        </div>
    </div>

    @include('jobs-monitor::partials.workers-auto-refresh')

    {{-- Block 1: Alive workers --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border bg-success/5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-success/15 text-success ring-1 ring-inset ring-success/20">
                <i data-lucide="heart-pulse" class="text-[16px]"></i>
            </span>
            <div class="flex-1">
                <h2 class="text-sm font-semibold">Alive workers</h2>
                <p class="text-xs text-muted-foreground">{{ number_format($vm->aliveTotal) }} worker(s) checked in within threshold</p>
            </div>
        </div>

        @if(count($vm->alive) === 0)
            <p class="px-5 py-6 text-sm text-muted-foreground">No alive workers observed.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-5 py-2.5 text-left font-medium">Worker</th>
                            <th class="px-5 py-2.5 text-left font-medium">Queue</th>
                            <th class="px-5 py-2.5 text-left font-medium">Host / PID</th>
                            <th class="px-5 py-2.5 text-left font-medium">Last seen</th>
                            <th class="px-5 py-2.5 text-left font-medium">Status</th>
                            <th class="px-5 py-2.5 text-right font-medium w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vm->alive as $worker)
                            @php $hb = $worker->heartbeat(); @endphp
                            <tr class="border-t border-border">
                                <td class="px-5 py-2.5 font-mono text-xs">{{ $hb->workerId->value }}</td>
                                <td class="px-5 py-2.5">{{ $hb->queueKey() }}</td>
                                <td class="px-5 py-2.5 text-muted-foreground tabular-nums">{{ $hb->host }} <span class="text-muted-foreground/60">· {{ $hb->pid }}</span></td>
                                <td class="px-5 py-2.5 tabular-nums">{{ $formatAge($hb->lastSeenAt, $vm->now) }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border {{ $statusBadges['alive'] }}">
                                        <i data-lucide="check-circle-2" class="text-[12px]"></i>
                                        Alive
                                    </span>
                                </td>
                                <td class="px-5 py-2.5" onclick="event.stopPropagation()">
                                    @include('jobs-monitor::partials.kebab-actions', [
                                        'actions' => [],
                                        'emptyLabel' => 'no actions',
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('jobs-monitor::partials.pagination', [
                'routeName' => 'jobs-monitor.workers',
                'currentPage' => $vm->alivePage,
                'lastPage' => $vm->aliveLastPage,
                'pageParam' => 'page',
                'extraParams' => $aliveExtraParams,
            ])
        @endif
    </section>

    {{-- Block 2: Silent workers --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden" id="workers-silent">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border bg-warning/5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/15 text-warning ring-1 ring-inset ring-warning/20">
                <i data-lucide="bell-off" class="text-[16px]"></i>
            </span>
            <div class="flex-1">
                <h2 class="text-sm font-semibold">Silent workers</h2>
                <p class="text-xs text-muted-foreground">{{ number_format($vm->silentTotal) }} worker(s) missed the {{ $vm->silentAfterSeconds }}s threshold</p>
            </div>
        </div>

        @if(count($vm->silent) === 0)
            <p class="px-5 py-6 text-sm text-muted-foreground">No silent workers — every worker is alive or intentionally stopped.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-5 py-2.5 text-left font-medium">Worker</th>
                            <th class="px-5 py-2.5 text-left font-medium">Queue</th>
                            <th class="px-5 py-2.5 text-left font-medium">Host / PID</th>
                            <th class="px-5 py-2.5 text-left font-medium">Last seen</th>
                            <th class="px-5 py-2.5 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vm->silent as $worker)
                            @php $hb = $worker->heartbeat(); @endphp
                            <tr class="border-t border-border">
                                <td class="px-5 py-2.5 font-mono text-xs">{{ $hb->workerId->value }}</td>
                                <td class="px-5 py-2.5">{{ $hb->queueKey() }}</td>
                                <td class="px-5 py-2.5 text-muted-foreground tabular-nums">{{ $hb->host }} <span class="text-muted-foreground/60">· {{ $hb->pid }}</span></td>
                                <td class="px-5 py-2.5 tabular-nums text-warning">{{ $formatAge($hb->lastSeenAt, $vm->now) }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border {{ $statusBadges['silent'] }}">
                                        <i data-lucide="bell-off" class="text-[12px]"></i>
                                        Silent
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('jobs-monitor::partials.pagination', [
                'routeName' => 'jobs-monitor.workers',
                'currentPage' => $vm->silentPage,
                'lastPage' => $vm->silentLastPage,
                'pageParam' => 'spage',
                'extraParams' => $silentExtraParams,
            ])
        @endif
    </section>

    {{-- Block 3: Dead workers --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden" id="workers-dead">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border bg-destructive/5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-destructive/15 text-destructive ring-1 ring-inset ring-destructive/20">
                <i data-lucide="skull" class="text-[16px]"></i>
            </span>
            <div class="flex-1">
                <h2 class="text-sm font-semibold">Dead workers</h2>
                <p class="text-xs text-muted-foreground">{{ number_format($vm->deadTotal) }} worker(s) stopped or offline far past the threshold</p>
            </div>
        </div>

        @if(count($vm->dead) === 0)
            <p class="px-5 py-6 text-sm text-muted-foreground">No dead workers — nothing has been stopped or crashed beyond the dead multiplier.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-5 py-2.5 text-left font-medium">Worker</th>
                            <th class="px-5 py-2.5 text-left font-medium">Queue</th>
                            <th class="px-5 py-2.5 text-left font-medium">Host / PID</th>
                            <th class="px-5 py-2.5 text-left font-medium">Last seen</th>
                            <th class="px-5 py-2.5 text-left font-medium">Stopped at</th>
                            <th class="px-5 py-2.5 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vm->dead as $worker)
                            @php $hb = $worker->heartbeat(); @endphp
                            <tr class="border-t border-border">
                                <td class="px-5 py-2.5 font-mono text-xs">{{ $hb->workerId->value }}</td>
                                <td class="px-5 py-2.5">{{ $hb->queueKey() }}</td>
                                <td class="px-5 py-2.5 text-muted-foreground tabular-nums">{{ $hb->host }} <span class="text-muted-foreground/60">· {{ $hb->pid }}</span></td>
                                <td class="px-5 py-2.5 tabular-nums text-destructive">{{ $formatAge($hb->lastSeenAt, $vm->now) }}</td>
                                <td class="px-5 py-2.5 tabular-nums text-muted-foreground">
                                    @if($worker->stoppedAt())
                                        {{ $worker->stoppedAt()->format('Y-m-d H:i:s') }}
                                    @else
                                        <span class="text-destructive/60">crashed</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border {{ $statusBadges['dead'] }}">
                                        <i data-lucide="skull" class="text-[12px]"></i>
                                        Dead
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('jobs-monitor::partials.pagination', [
                'routeName' => 'jobs-monitor.workers',
                'currentPage' => $vm->deadPage,
                'lastPage' => $vm->deadLastPage,
                'pageParam' => 'dpage',
                'extraParams' => $deadExtraParams,
            ])
        @endif
    </section>

    {{-- Block 4: Queue coverage --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden" id="workers-coverage">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border bg-info/5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-info/15 text-info ring-1 ring-inset ring-info/20">
                <i data-lucide="layers" class="text-[16px]"></i>
            </span>
            <div class="flex-1">
                <h2 class="text-sm font-semibold">Queue coverage</h2>
                <p class="text-xs text-muted-foreground">Observed vs expected alive workers per queue</p>
            </div>
        </div>

        @if(count($vm->coverage) === 0)
            <div class="px-5 py-6 text-sm text-muted-foreground">
                No expectations configured. Add a <code class="text-xs bg-muted px-1 py-0.5 rounded">workers.expected</code> map in <code class="text-xs bg-muted px-1 py-0.5 rounded">config/jobs-monitor.php</code> to enable under-provisioned alerts.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-5 py-2.5 text-left font-medium">Queue</th>
                            <th class="px-5 py-2.5 text-left font-medium">Observed</th>
                            <th class="px-5 py-2.5 text-left font-medium">Expected</th>
                            <th class="px-5 py-2.5 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vm->coverage as $row)
                            <tr class="border-t border-border">
                                <td class="px-5 py-2.5 font-mono text-xs">{{ $row['queue_key'] }}</td>
                                <td class="px-5 py-2.5 tabular-nums">{{ $row['observed'] }}</td>
                                <td class="px-5 py-2.5 tabular-nums">{{ $row['expected'] }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border {{ $coverageBadges[$row['status']] }}">
                                        {{ strtoupper($row['status']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('jobs-monitor::partials.pagination', [
                'routeName' => 'jobs-monitor.workers',
                'currentPage' => $vm->coveragePage,
                'lastPage' => $vm->coverageLastPage,
                'pageParam' => 'ppage',
                'extraParams' => $coverageExtraParams,
            ])
        @endif
    </section>
</div>

@include('jobs-monitor::partials.kebab-script')
@endsection
