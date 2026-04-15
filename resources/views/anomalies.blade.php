@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-success/30 bg-success/10 text-success px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-destructive/30 bg-destructive/10 text-destructive px-4 py-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
                <i data-lucide="trending-down" class="text-brand text-[22px]"></i>
                Duration anomalies
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Successful jobs whose duration fell wildly outside the historical p50/p95 envelope.
            </p>
        </div>
        <form method="POST" action="{{ route('jobs-monitor.anomalies.refresh-baselines') }}" class="flex items-center gap-2">
            @csrf
            <label class="text-xs text-muted-foreground hidden sm:inline">Lookback (days)</label>
            <input type="number"
                   name="lookback_days"
                   min="1"
                   max="90"
                   value="7"
                   class="h-9 w-20 rounded-md border border-input bg-card text-sm text-foreground px-2 tabular-nums focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md border border-brand/30 bg-brand/10 text-brand text-sm font-medium hover:bg-brand/15 hover:border-brand/40 transition-colors shadow-xs">
                <i data-lucide="refresh-cw" class="text-[14px]"></i>
                Refresh baselines now
            </button>

            {{-- Hover-tooltip: explains what the button actually triggers, so
                 operators don't need to ssh in to read the source to feel safe. --}}
            <div class="relative group inline-flex" tabindex="0">
                <button type="button"
                        aria-label="What does this button do?"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <i data-lucide="info" class="text-[15px]"></i>
                </button>
                <div role="tooltip"
                     class="pointer-events-none invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 transition-opacity duration-150
                            absolute right-0 top-full mt-2 w-80 z-30
                            rounded-lg border border-border bg-popover text-popover-foreground shadow-lg ring-1 ring-black/5 dark:ring-white/5 p-3 text-left">
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-foreground mb-1.5">
                        <i data-lucide="terminal" class="text-[13px] text-brand"></i>
                        Equivalent to running
                    </div>
                    <code class="block w-full bg-muted text-foreground text-[11px] font-mono px-2 py-1.5 rounded mb-2 break-all">php artisan jobs-monitor:refresh-duration-baselines --lookback-days=&lt;N&gt;</code>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Recomputes the <span class="font-medium text-foreground">p50/p95</span> duration baseline per job class
                        from successful runs in the chosen window, then writes them to
                        <code class="px-1 py-0.5 rounded bg-muted text-[10.5px]">jobs_monitor_duration_baselines</code>.
                        The next anomaly check uses these baselines to flag outliers.
                    </p>
                    <p class="text-[10.5px] text-muted-foreground/80 mt-2 italic">
                        Read-only on <code class="text-[10.5px]">jobs_monitor</code>; only updates the baselines table. Safe to run anytime.
                    </p>
                </div>
            </div>
        </form>
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
    <section class="rounded-xl border border-border bg-card overflow-hidden" data-collapsible="anomalies-baselines">
        <button type="button"
                class="w-full flex items-center gap-3 px-5 py-3.5 border-b border-border text-left bg-brand/5 hover:bg-brand/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onclick="__jmToggleCollapsible('anomalies-baselines')"
                data-collapsible-trigger>
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/15 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="bar-chart-2" class="text-[16px]"></i>
            </span>
            <div class="flex-1">
                <h2 class="text-sm font-semibold">Baselines per job class</h2>
                <p class="text-xs text-muted-foreground">{{ $vm->baselines->count() }} class(es)</p>
            </div>
            <span class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground" data-collapsible-label>Hide</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:text-foreground transition-transform" data-collapsible-caret>
                <i data-lucide="chevron-up" class="text-[16px]"></i>
            </span>
        </button>
        <div data-collapsible-body>
        @if ($vm->baselines->isEmpty())
            <div class="px-5 py-12">
                <div class="max-w-xl mx-auto text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                        <i data-lucide="bar-chart-2" class="text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-foreground">No baselines yet</p>
                    <p class="text-xs text-muted-foreground mt-2 leading-relaxed">
                        This table is empty on a fresh install — it gets filled the first time
                        the baseline refresher runs over your <span class="font-medium text-foreground">successful</span> job history.
                    </p>
                    <div class="mt-4 text-left rounded-lg border border-border bg-muted/30 p-3 text-xs text-muted-foreground">
                        <p class="font-semibold text-foreground mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="check-square" class="text-[14px] text-brand"></i>
                            Conditions for it to populate
                        </p>
                        <ol class="list-decimal pl-5 space-y-1">
                            <li>You have at least one job class with successful runs in the lookback window (default 7 days).</li>
                            <li>Either the scheduled command has run for the first time
                                (<code class="px-1 py-0.5 rounded bg-muted">jobs-monitor:refresh-duration-baselines</code>, recommended <em>daily</em>),
                                or you click <span class="font-medium text-foreground">"Refresh baselines now"</span> above.</li>
                        </ol>
                        <p class="mt-2">Until then anomaly detection is a no-op — there's nothing to compare against, so nothing gets flagged.</p>
                    </div>
                </div>
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
                            <tr class="{{ $loop->even ? 'bg-muted/40 hover:bg-muted/60' : 'bg-card hover:bg-muted/30' }} transition-colors">
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
        </div>
    </section>

    {{-- Recent anomalies --}}
    <section class="rounded-xl border border-border bg-card overflow-hidden" data-collapsible="anomalies-recent">
        <button type="button"
                class="w-full flex items-center gap-3 px-5 py-3.5 border-b border-border text-left bg-warning/5 hover:bg-warning/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onclick="__jmToggleCollapsible('anomalies-recent')"
                data-collapsible-trigger>
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/15 text-warning ring-1 ring-inset ring-warning/20">
                <i data-lucide="alert-triangle" class="text-[16px]"></i>
            </span>
            <div class="flex-1">
                <h2 class="text-sm font-semibold">Recent anomalies</h2>
                <p class="text-xs text-muted-foreground">{{ number_format($vm->anomaliesTotal) }} total · 50 per page</p>
            </div>
            <span class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground" data-collapsible-label>Hide</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:text-foreground transition-transform" data-collapsible-caret>
                <i data-lucide="chevron-up" class="text-[16px]"></i>
            </span>
        </button>
        <div data-collapsible-body>
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
                            <th class="px-5 py-2 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($vm->recentAnomalies as $a)
                            @php
                                $kindTone = $a->kind === 'short'
                                    ? 'bg-info/10 text-info border-info/20'
                                    : 'bg-warning/10 text-warning border-warning/20';
                                $kindIcon = $a->kind === 'short' ? 'zap' : 'hourglass';
                                $key = \Yammi\JobsMonitor\Presentation\ViewModel\DurationAnomaliesViewModel::jobRecordKey($a->job_uuid, $a->attempt);
                                $job = $vm->jobRecordsByKey[$key] ?? null;
                            @endphp
                            <tr class="cursor-pointer transition-colors {{ $loop->even ? 'bg-muted/40 hover:bg-muted/60' : 'bg-card hover:bg-muted/30' }}"
                                onclick="this.nextElementSibling.classList.toggle('hidden')">
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
                                <td class="px-5 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    @if ($job !== null && ! empty($job->payload))
                                        <div class="inline-flex items-center gap-1">
                                            <form method="POST" action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $job->uuid]) }}" class="inline-block">
                                                @csrf
                                                <button type="submit"
                                                        title="Retry this job now with the stored payload"
                                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-primary hover:bg-primary/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                    <i data-lucide="refresh-cw" class="text-[14px]"></i>
                                                    <span class="sr-only">Retry</span>
                                                </button>
                                            </form>
                                            <a href="{{ route('jobs-monitor.dlq.edit', ['uuid' => $job->uuid]) }}"
                                               title="Edit payload and retry"
                                               class="inline-flex h-7 w-7 items-center justify-center rounded-md text-brand hover:bg-brand/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <i data-lucide="pencil" class="text-[14px]"></i>
                                                <span class="sr-only">Edit &amp; retry</span>
                                            </a>
                                        </div>
                                    @elseif ($job === null)
                                        <span class="text-[11px] text-muted-foreground italic" title="Original job pruned by retention">pruned</span>
                                    @else
                                        <span class="text-[11px] text-muted-foreground italic" title="Set JOBS_MONITOR_STORE_PAYLOAD=true to enable retry">no payload</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="hidden">
                                <td colspan="6" class="px-5 py-4 bg-muted/30 animate-slide-down">
                                    @include('jobs-monitor::partials.anomaly-detail', ['anomaly' => $a, 'job' => $job])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($vm->lastPage > 1)
                @include('jobs-monitor::partials.pagination', [
                    'currentPage' => $vm->page,
                    'lastPage' => $vm->lastPage,
                    'pageParam' => 'page',
                    'extraParams' => [],
                    'routeName' => 'jobs-monitor.anomalies',
                ])
            @endif
        @endif
        </div>
    </section>
</div>

<script>
    if (!window.__jmToggleCollapsible) {
        window.__jmToggleCollapsible = function (key) {
            var root = document.querySelector('[data-collapsible="' + key + '"]');
            if (!root) return;
            var collapsed = root.getAttribute('data-collapsed') === '1';
            var body = root.querySelector('[data-collapsible-body]');
            var caret = root.querySelector('[data-collapsible-caret]');
            var label = root.querySelector('[data-collapsible-label]');
            root.setAttribute('data-collapsed', collapsed ? '0' : '1');
            if (body) body.classList.toggle('hidden', !collapsed);
            if (caret) caret.style.transform = collapsed ? 'rotate(0deg)' : 'rotate(180deg)';
            if (label) label.textContent = collapsed ? 'Hide' : 'Show';
            try { localStorage.setItem('jm-collapsed-' + key, collapsed ? '0' : '1'); } catch (e) {}
        };
    }
    (function () {
        function hydrate() {
            document.querySelectorAll('[data-collapsible]').forEach(function (root) {
                var key = root.getAttribute('data-collapsible');
                var stored = null;
                try { stored = localStorage.getItem('jm-collapsed-' + key); } catch (e) {}
                if (stored === '1') {
                    var body = root.querySelector('[data-collapsible-body]');
                    var caret = root.querySelector('[data-collapsible-caret]');
                    var label = root.querySelector('[data-collapsible-label]');
                    root.setAttribute('data-collapsed', '1');
                    if (body) body.classList.add('hidden');
                    if (caret) caret.style.transform = 'rotate(180deg)';
                    if (label) label.textContent = 'Show';
                }
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hydrate);
        } else {
            hydrate();
        }
    })();
</script>
@endsection
