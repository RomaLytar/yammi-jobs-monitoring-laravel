@extends('jobs-monitor::layouts.app')

@section('content')
    {{-- Page header --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20">
                <i data-lucide="skull" class="text-[18px]"></i>
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dead Letter Queue</h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Jobs that exhausted all retries (attempt ≥ {{ $vm->maxTries }}) or failed with a permanent / critical category.
                </p>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('status'))
        <div class="flex items-start gap-3 rounded-lg border border-success/25 bg-success/10 text-success px-4 py-3 text-sm mb-4">
            <i data-lucide="check-circle-2" class="text-[16px] mt-0.5"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 text-destructive px-4 py-3 text-sm mb-4">
            <i data-lucide="alert-circle" class="text-[16px] mt-0.5"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    @if(! $vm->retryEnabled)
        <div class="flex items-start gap-3 rounded-lg border border-warning/25 bg-warning/10 text-warning-foreground dark:text-warning px-4 py-3 text-sm mb-4">
            <i data-lucide="alert-triangle" class="text-[16px] mt-0.5 text-warning"></i>
            <div>
                Retry is disabled because payloads are not stored.
                Set <code class="px-1.5 py-0.5 rounded bg-card border border-border text-xs font-mono">JOBS_MONITOR_STORE_PAYLOAD=true</code> in the host app to enable re-dispatch.
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <i data-lucide="archive" class="text-[16px]"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold">
                        {{ number_format($vm->total) }} dead {{ $vm->total === 1 ? 'entry' : 'entries' }}
                    </h2>
                    <p class="text-xs text-muted-foreground">Sorted by last failure</p>
                </div>
            </div>
            @if($vm->total > 0)
                @include('jobs-monitor::partials.button', [
                    'variant' => 'secondary',
                    'size' => 'sm',
                    'icon' => 'check-check',
                    'label' => 'Select all '.number_format($vm->total).' matching',
                    'attrs' => 'data-jm-bulk-select-all="dlq" title="Select every dead-letter entry across all pages"',
                ])
            @endif
        </div>

        @if(count($vm->jobs) === 0)
            <div class="px-5 py-16 text-center">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success mb-3">
                    <i data-lucide="shield-check" class="text-xl"></i>
                </div>
                <p class="text-sm font-medium">All clear</p>
                <p class="text-xs text-muted-foreground mt-1">No dead-letter jobs. Everything eventually succeeded or is still retryable.</p>
            </div>
        @else
            <table class="w-full text-sm table-fixed"
                   data-jm-bulk-scope="dlq"
                   data-jm-bulk-candidates="{{ route('jobs-monitor.dlq.bulk.candidates') }}"
                   data-jm-bulk-retry="{{ route('jobs-monitor.dlq.bulk.retry') }}"
                   data-jm-bulk-delete="{{ route('jobs-monitor.dlq.bulk.delete') }}"
                   data-jm-bulk-noun="entry">
                <colgroup>
                    <col class="w-10">
                    <col>
                    <col class="w-[90px]">
                    <col class="hidden md:table-column w-[70px]">
                    <col class="hidden md:table-column w-[110px]">
                    <col class="w-[130px]">
                    <col class="hidden xl:table-column">
                    <col class="w-12">
                </colgroup>
                <thead>
                    <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                        <th class="px-3 py-2.5">
                            @include('jobs-monitor::partials.checkbox', [
                                'ariaLabel' => 'Select all on page',
                                'attributes' => 'data-jm-bulk-page-select',
                            ])
                        </th>
                        <th class="text-left font-medium px-3 py-2.5">Job</th>
                        <th class="text-left font-medium px-3 py-2.5">Queue</th>
                        <th class="hidden md:table-cell text-left font-medium px-3 py-2.5">Att.</th>
                        <th class="hidden md:table-cell text-left font-medium px-3 py-2.5">Category</th>
                        <th class="text-left font-medium px-3 py-2.5">Last failed</th>
                        <th class="hidden xl:table-cell text-left font-medium px-3 py-2.5">Exception</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($vm->jobs as $job)
                        <tr class="cursor-pointer {{ $loop->even ? 'bg-muted/40' : 'bg-card' }} hover:bg-muted/60 transition-colors" onclick="this.nextElementSibling.classList.toggle('hidden')">
                            <td class="px-3 py-3 align-middle" onclick="event.stopPropagation()">
                                @include('jobs-monitor::partials.checkbox', [
                                    'value' => $job['uuid'],
                                    'ariaLabel' => 'Select '.$job['short_class'],
                                    'attributes' => 'data-jm-bulk-row data-retryable="'.(($vm->retryEnabled && $job['has_payload']) ? '1' : '0').'"',
                                ])
                            </td>
                            <td class="px-3 py-3 font-medium truncate" title="{{ $job['job_class'] }}">{{ $job['short_class'] }}</td>
                            <td class="px-3 py-3 text-muted-foreground"><code class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">{{ $job['queue'] }}</code></td>
                            <td class="hidden md:table-cell px-3 py-3 text-muted-foreground tabular-nums">{{ $job['attempt'] }}</td>
                            <td class="hidden md:table-cell px-3 py-3">
                                @include('jobs-monitor::partials.failure-category-badge', [
                                    'value' => $job['failure_category'],
                                    'label' => $job['failure_category_label'],
                                ])
                            </td>
                            <td class="px-3 py-3 text-muted-foreground tabular-nums text-xs">{{ $job['finished_at'] ?? $job['started_at'] }}</td>
                            <td class="hidden xl:table-cell px-3 py-3 text-destructive truncate text-xs" title="{{ $job['exception'] ?? '' }}">{{ \Illuminate\Support\Str::limit($job['exception'] ?? '', 50) }}</td>
                            <td class="px-3 py-3 text-right" onclick="event.stopPropagation()">
                                @include('jobs-monitor::partials.retry-actions', ['job' => $job, 'retryEnabled' => $vm->retryEnabled])
                            </td>
                        </tr>
                        <tr class="hidden">
                            <td colspan="8" class="px-5 py-4 bg-muted/30 animate-slide-down">
                                <div class="flex justify-end mb-3">
                                    @include('jobs-monitor::partials.button', [
                                        'as' => 'link',
                                        'href' => route('jobs-monitor.detail', ['uuid' => $job['uuid'], 'attempt' => $job['attempt']]),
                                        'variant' => 'primary',
                                        'size' => 'sm',
                                        'icon' => 'arrow-right',
                                        'label' => 'View details & retry timeline',
                                    ])
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach([
                                        ['UUID', $job['uuid'], true],
                                        ['Full class', $job['job_class'], true],
                                        ['Connection', $job['connection'], false],
                                        ['Started at', $job['started_at'], false],
                                    ] as [$label, $val, $mono])
                                        <div>
                                            <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">{{ $label }}</span>
                                            <p class="text-sm {{ $mono ? 'font-mono break-all' : '' }}">{{ $val }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                @if($job['payload'])
                                    <div class="mt-3">
                                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Payload</span>
                                        <pre class="mt-1 bg-card border border-border rounded-lg p-3 text-xs overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ json_encode($job['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                                @if($job['exception'])
                                    <div class="mt-3">
                                        <span class="text-[10px] font-medium text-destructive uppercase tracking-wider">Exception</span>
                                        <pre class="mt-1 bg-destructive/10 border border-destructive/20 rounded-lg p-3 text-xs text-destructive overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ $job['exception'] }}</pre>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($vm->lastPage > 1)
                @include('jobs-monitor::partials.pagination', [
                    'currentPage' => $vm->page,
                    'lastPage' => $vm->lastPage,
                    'pageParam' => 'page',
                    'extraParams' => [],
                    'routeName' => 'jobs-monitor.dlq',
                ])
            @endif
        @endif
    </div>

    @include('jobs-monitor::partials.bulk-bar', [
        'scope' => 'dlq',
        'retryEnabled' => $vm->retryEnabled,
        'showDelete' => true,
        'noun' => 'entry',
    ])

    @include('jobs-monitor::partials.bulk-script')
    @include('jobs-monitor::partials.kebab-script')
    @include('jobs-monitor::partials.confirm-modal')
@endsection
