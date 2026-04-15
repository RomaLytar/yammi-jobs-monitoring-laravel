@extends('jobs-monitor::layouts.app')

@section('content')
@php
    $statusOptions = [
        '' => 'All statuses',
        'success' => 'Success',
        'failed' => 'Failed',
        'late' => 'Late',
        'running' => 'Running',
        'skipped' => 'Skipped',
    ];

    $statusCards = [
        ['key' => 'success', 'label' => 'Success', 'icon' => 'check-circle-2', 'iconBg' => 'bg-success/10 text-success',         'accent' => 'text-success'],
        ['key' => 'failed',  'label' => 'Failed',  'icon' => 'x-circle',       'iconBg' => 'bg-destructive/10 text-destructive', 'accent' => 'text-destructive'],
        ['key' => 'late',    'label' => 'Late',    'icon' => 'alarm-clock',    'iconBg' => 'bg-warning/10 text-warning',         'accent' => 'text-warning'],
        ['key' => 'running', 'label' => 'Running', 'icon' => 'loader',         'iconBg' => 'bg-info/10 text-info',               'accent' => 'text-info'],
        ['key' => 'skipped', 'label' => 'Skipped', 'icon' => 'minus-circle',   'iconBg' => 'bg-muted text-muted-foreground',     'accent' => 'text-muted-foreground'],
    ];

    $statusBadges = [
        'success' => 'bg-success/10 text-success border-success/20',
        'failed'  => 'bg-destructive/10 text-destructive border-destructive/20',
        'late'    => 'bg-warning/10 text-warning border-warning/20',
        'running' => 'bg-info/10 text-info border-info/20',
        'skipped' => 'bg-muted text-muted-foreground border-border',
    ];
    $statusIcons = [
        'success' => 'check-circle-2',
        'failed'  => 'x-circle',
        'late'    => 'alarm-clock',
        'running' => 'loader',
        'skipped' => 'minus-circle',
    ];

    $baseParams = array_filter([
        'status' => $vm->status,
        'search' => $vm->search,
        'sort' => $vm->sort,
        'dir' => $vm->dir,
    ], static fn ($v) => $v !== '' && $v !== null);

    $sortUrl = fn (string $col) => route('jobs-monitor.scheduled', array_merge($baseParams, [
        'sort' => $col,
        'dir' => ($vm->sort === $col && $vm->dir === 'asc') ? 'desc' : 'asc',
        'page' => 1,
    ]));
    $sortIcon = fn (string $col) => $vm->sort === $col
        ? ($vm->dir === 'asc' ? 'arrow-up' : 'arrow-down')
        : 'chevrons-up-down';
    $sortClass = fn (string $col) => $vm->sort === $col
        ? 'text-foreground'
        : 'text-muted-foreground';

    $activeFilters = array_values(array_filter([
        ['key' => 'search', 'name' => 'Search', 'value' => $vm->search],
        ['key' => 'status', 'name' => 'Status', 'value' => $vm->status],
    ], static fn (array $f) => $f['value'] !== ''));

    $inputBase = 'h-9 rounded-md border border-input bg-card text-sm text-foreground px-3 focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring transition-[box-shadow,border-color]';
    $inputActive = 'border-brand ring-2 ring-brand/20 bg-brand/5';

    $isArtisan = function ($run): bool {
        $cmd = $run->command ?? null;
        $name = $run->taskName ?? '';
        return (is_string($cmd) && str_contains($cmd, 'artisan'))
            || (is_string($name) && str_contains($name, 'artisan'));
    };
@endphp

{{-- Flash messages --}}
@if (session('status'))
    <div class="rounded-lg border border-success/30 bg-success/10 text-success px-4 py-3 mb-4 text-sm whitespace-pre-line">
        {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div class="rounded-lg border border-destructive/30 bg-destructive/10 text-destructive px-4 py-3 mb-4 text-sm">
        {{ $errors->first() }}
    </div>
@endif

{{-- Page header --}}
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
            <i data-lucide="calendar-clock" class="text-brand text-[22px]"></i>
            Scheduled tasks
        </h1>
        <p class="text-sm text-muted-foreground mt-1">
            Every Laravel scheduler run captured by the monitor. Late = stayed in Running past tolerance (worker/host crash).
        </p>
    </div>
    <a href="{{ url()->full() }}"
       class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md border border-border bg-card text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors shadow-xs">
        <i data-lucide="refresh-cw" class="text-[14px]"></i>
        Refresh
    </a>
</div>

{{-- Status counter cards --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
    @foreach ($statusCards as $card)
        @php
            $count = $vm->statusCounts[$card['key']] ?? 0;
            $isActive = $vm->status === $card['key'];
        @endphp
        <a href="{{ route('jobs-monitor.scheduled', array_merge($baseParams, ['status' => $isActive ? '' : $card['key'], 'page' => 1])) }}"
           class="group relative overflow-hidden rounded-xl border bg-card text-card-foreground p-4 shadow-xs transition-all hover:shadow-md
                  {{ $isActive ? 'border-brand/60 ring-2 ring-brand/20' : 'border-border' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">{{ $card['label'] }}</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-md {{ $card['iconBg'] }}">
                    <i data-lucide="{{ $card['icon'] }}" class="text-[14px] {{ $card['key'] === 'running' && $count > 0 ? 'animate-spin' : '' }}"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $count > 0 ? $card['accent'] : 'text-foreground' }}">{{ number_format($count) }}</div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-8 -right-8 h-24 w-24 rounded-full bg-gradient-to-tr from-transparent to-brand/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        </a>
    @endforeach
</div>

{{-- Failed (10 per page, separate pagination) --}}
@if ($vm->failedTotal > 0)
    <div class="rounded-xl border border-destructive/30 bg-card text-card-foreground shadow-xs mb-6 overflow-hidden" data-collapsible="scheduled-failed">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-destructive/20 bg-destructive/5">
            <button type="button"
                    class="flex-1 flex items-center gap-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md"
                    onclick="__jmToggleCollapsible('scheduled-failed')"
                    data-collapsible-trigger>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-destructive/15 text-destructive ring-1 ring-inset ring-destructive/20">
                    <i data-lucide="alert-triangle" class="text-[16px]"></i>
                </span>
                <div class="flex-1">
                    <h2 class="text-sm font-semibold text-destructive">Failed</h2>
                    <p class="text-xs text-muted-foreground">{{ number_format($vm->failedTotal) }} failed scheduled-task run(s)</p>
                </div>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground" data-collapsible-label>Hide</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:text-foreground transition-transform" data-collapsible-caret>
                    <i data-lucide="chevron-up" class="text-[16px]"></i>
                </span>
            </button>
        </div>
        <div data-collapsible-body>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                            <th class="text-left font-medium px-5 py-2.5">Task</th>
                            <th class="text-left font-medium px-5 py-2.5">Cron</th>
                            <th class="text-left font-medium px-5 py-2.5">Failed at</th>
                            <th class="text-left font-medium px-5 py-2.5">Duration</th>
                            <th class="text-left font-medium px-5 py-2.5">Exception</th>
                            <th class="text-right font-medium px-5 py-2.5">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($vm->failedRows as $run)
                            @php $rowId = $vm->rowIds[$vm->rowKey($run)] ?? null; @endphp
                            <tr class="cursor-pointer {{ $loop->even ? 'bg-destructive/10' : 'bg-destructive/5' }} hover:bg-destructive/15 transition-colors"
                                onclick="this.nextElementSibling.classList.toggle('hidden')">
                                <td class="px-5 py-3 font-medium truncate max-w-xs" title="{{ $run->taskName }}">{{ $run->taskName }}</td>
                                <td class="px-5 py-3"><code class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">{{ $run->expression }}</code></td>
                                <td class="px-5 py-3 text-muted-foreground tabular-nums text-xs">{{ ($run->finishedAt() ?? $run->startedAt)->format('Y-m-d H:i:s') }}</td>
                                <td class="px-5 py-3 tabular-nums text-xs">
                                    @if ($run->duration() !== null)
                                        {{ number_format($run->duration()->milliseconds) }} ms
                                    @else
                                        <span class="text-muted-foreground">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-destructive text-xs truncate max-w-md" title="{{ $run->exception() }}">
                                    {{ $run->exception() ? \Illuminate\Support\Str::limit($run->exception(), 80) : '' }}
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    @if ($rowId !== null && $isArtisan($run))
                                        <form method="POST" action="{{ route('jobs-monitor.scheduled.retry', ['id' => $rowId]) }}" class="inline-block">
                                            @csrf
                                            <button type="submit"
                                                    title="Re-run this scheduled task now"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-primary hover:bg-primary/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <i data-lucide="refresh-cw" class="text-[14px]"></i>
                                                <span class="sr-only">Retry</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[11px] text-muted-foreground italic" title="Re-run only available for artisan commands">non-artisan</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="hidden">
                                <td colspan="6" class="px-5 py-4 bg-muted/30 animate-slide-down">
                                    @include('jobs-monitor::partials.scheduled-detail', ['run' => $run])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($vm->failedLastPage > 1)
                @include('jobs-monitor::partials.pagination', [
                    'currentPage' => $vm->failedPage,
                    'lastPage' => $vm->failedLastPage,
                    'pageParam' => 'fpage',
                    'extraParams' => array_merge($baseParams, ['page' => $vm->page]),
                    'routeName' => 'jobs-monitor.scheduled',
                ])
            @endif
        </div>
    </div>
@endif

{{-- Filters --}}
<div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs mb-6">
    <div class="p-4">
        <form method="GET" action="{{ route('jobs-monitor.scheduled') }}" class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <i data-lucide="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[14px] text-muted-foreground pointer-events-none"></i>
                <input type="text" name="search" value="{{ $vm->search }}" placeholder="Search by task name, mutex or cron…"
                       class="pl-8 w-72 {{ $inputBase }} {{ $vm->search !== '' ? $inputActive : '' }}">
            </div>

            @include('jobs-monitor::partials.select', [
                'name' => 'status', 'value' => $vm->status, 'options' => $statusOptions, 'placeholder' => 'All statuses',
            ])

            <input type="hidden" name="sort" value="{{ $vm->sort }}">
            <input type="hidden" name="dir" value="{{ $vm->dir }}">

            <button type="submit"
                    class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-md border border-brand/30 bg-brand/10 text-brand text-sm font-medium hover:bg-brand/15 hover:border-brand/40 transition-colors">
                <i data-lucide="filter" class="text-[14px]"></i>
                Apply
            </button>

            @if (count($activeFilters) > 0)
                <a href="{{ route('jobs-monitor.scheduled') }}"
                   class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground px-2 py-1.5">
                    <i data-lucide="x" class="text-[13px]"></i>
                    Clear all
                </a>
            @endif
        </form>

        @if (count($activeFilters) > 0)
            <div class="flex flex-wrap items-center gap-1.5 pt-3">
                @foreach ($activeFilters as $filter)
                    @php
                        $removeParams = $baseParams;
                        unset($removeParams[$filter['key']]);
                    @endphp
                    <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1 py-1 rounded-md text-xs font-medium bg-secondary text-secondary-foreground ring-1 ring-inset ring-border">
                        <span class="text-muted-foreground">{{ $filter['name'] }}:</span>
                        <span class="font-semibold">{{ $filter['value'] }}</span>
                        <a href="{{ route('jobs-monitor.scheduled', $removeParams) }}"
                           class="inline-flex items-center justify-center w-4 h-4 rounded-sm text-muted-foreground hover:bg-destructive/15 hover:text-destructive transition-colors">
                            <i data-lucide="x" class="text-[11px]"></i>
                        </a>
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Runs table --}}
<div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
            <i data-lucide="history" class="text-[16px]"></i>
        </span>
        <div>
            <h2 class="text-sm font-semibold">Runs</h2>
            <p class="text-xs text-muted-foreground">{{ number_format($vm->total) }} total · sorted by {{ $vm->sort }} ({{ $vm->dir }})</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                    <th class="text-left font-medium px-5 py-2.5">
                        <a href="{{ $sortUrl('status') }}" class="inline-flex items-center gap-1 hover:text-foreground {{ $sortClass('status') }}">
                            Status <i data-lucide="{{ $sortIcon('status') }}" class="text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-left font-medium px-5 py-2.5">
                        <a href="{{ $sortUrl('task_name') }}" class="inline-flex items-center gap-1 hover:text-foreground {{ $sortClass('task_name') }}">
                            Task <i data-lucide="{{ $sortIcon('task_name') }}" class="text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-left font-medium px-5 py-2.5">Cron</th>
                    <th class="text-left font-medium px-5 py-2.5">
                        <a href="{{ $sortUrl('started_at') }}" class="inline-flex items-center gap-1 hover:text-foreground {{ $sortClass('started_at') }}">
                            Started <i data-lucide="{{ $sortIcon('started_at') }}" class="text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-left font-medium px-5 py-2.5">
                        <a href="{{ $sortUrl('duration_ms') }}" class="inline-flex items-center gap-1 hover:text-foreground {{ $sortClass('duration_ms') }}">
                            Duration <i data-lucide="{{ $sortIcon('duration_ms') }}" class="text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-right font-medium px-5 py-2.5">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($vm->rows as $run)
                    @php
                        $statusKey = $run->status()->value;
                        $badge = $statusBadges[$statusKey] ?? $statusBadges['skipped'];
                        $icon = $statusIcons[$statusKey] ?? 'minus-circle';
                        $rowBg = $statusKey === 'failed'
                            ? 'bg-destructive/5 hover:bg-destructive/10'
                            : ($statusKey === 'late'
                                ? 'bg-warning/5 hover:bg-warning/10'
                                : ($loop->even ? 'bg-muted/40 hover:bg-muted/60' : 'bg-card hover:bg-muted/30'));
                        $rowId = $vm->rowIds[$vm->rowKey($run)] ?? null;
                        $canRetry = $rowId !== null && $isArtisan($run);
                    @endphp
                    <tr class="cursor-pointer transition-colors {{ $rowBg }}"
                        onclick="this.nextElementSibling.classList.toggle('hidden')">
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium border {{ $badge }}">
                                <i data-lucide="{{ $icon }}" class="text-[12px] {{ $statusKey === 'running' ? 'animate-spin' : '' }}"></i>
                                {{ $run->status()->label() }}
                            </span>
                        </td>
                        <td class="px-5 py-3 font-medium truncate max-w-xs" title="{{ $run->taskName }}">{{ $run->taskName }}</td>
                        <td class="px-5 py-3"><code class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">{{ $run->expression }}</code></td>
                        <td class="px-5 py-3 text-muted-foreground tabular-nums text-xs">{{ $run->startedAt->format('Y-m-d H:i:s') }}</td>
                        <td class="px-5 py-3 tabular-nums text-xs">
                            @if ($run->duration() !== null)
                                {{ number_format($run->duration()->milliseconds) }} ms
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                            @if ($canRetry)
                                <form method="POST" action="{{ route('jobs-monitor.scheduled.retry', ['id' => $rowId]) }}" class="inline-block">
                                    @csrf
                                    <button type="submit"
                                            title="Re-run this scheduled task now"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-primary hover:bg-primary/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                        <i data-lucide="refresh-cw" class="text-[14px]"></i>
                                        <span class="sr-only">Retry</span>
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr class="hidden">
                        <td colspan="6" class="px-5 py-4 bg-muted/30 animate-slide-down">
                            @include('jobs-monitor::partials.scheduled-detail', ['run' => $run])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                    <i data-lucide="calendar-x" class="text-xl"></i>
                                </div>
                                <p class="text-sm font-medium text-foreground">No scheduled-task runs</p>
                                <p class="text-xs">No runs match the current filters. Try clearing them, or check that <code class="px-1.5 py-0.5 rounded bg-muted">php artisan schedule:run</code> is on cron.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($vm->lastPage > 1)
        @include('jobs-monitor::partials.pagination', [
            'currentPage' => $vm->page,
            'lastPage' => $vm->lastPage,
            'pageParam' => 'page',
            'extraParams' => array_merge($baseParams, ['fpage' => $vm->failedPage]),
            'routeName' => 'jobs-monitor.scheduled',
        ])
    @endif
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
</script>
@endsection
