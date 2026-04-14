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
                <button type="button"
                        class="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-medium rounded-md border border-border bg-card hover:bg-accent transition-colors"
                        data-jm-bulk-select-all="dlq"
                        title="Select every dead-letter entry across all pages">
                    <i data-lucide="check-check" class="text-[14px]"></i>
                    Select all {{ number_format($vm->total) }} matching
                </button>
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
            <div class="overflow-x-auto">
                <table class="w-full text-sm"
                       data-jm-bulk-scope="dlq"
                       data-jm-bulk-candidates="{{ route('jobs-monitor.dlq.bulk.candidates') }}"
                       data-jm-bulk-retry="{{ route('jobs-monitor.dlq.bulk.retry') }}"
                       data-jm-bulk-delete="{{ route('jobs-monitor.dlq.bulk.delete') }}"
                       data-jm-bulk-noun="entry">
                    <thead>
                        <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                            <th class="w-10 px-5 py-2.5">
                                @include('jobs-monitor::partials.checkbox', [
                                    'ariaLabel' => 'Select all on page',
                                    'attributes' => 'data-jm-bulk-page-select',
                                ])
                            </th>
                            <th class="text-left font-medium px-5 py-2.5">Job</th>
                            <th class="text-left font-medium px-5 py-2.5">Queue</th>
                            <th class="text-left font-medium px-5 py-2.5">Attempts</th>
                            <th class="text-left font-medium px-5 py-2.5">Category</th>
                            <th class="text-left font-medium px-5 py-2.5">Last failed</th>
                            <th class="text-left font-medium px-5 py-2.5">Exception</th>
                            <th class="text-right font-medium px-5 py-2.5">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($vm->jobs as $job)
                            <tr class="cursor-pointer {{ $loop->even ? 'bg-muted/40' : 'bg-card' }} hover:bg-muted/60 transition-colors" onclick="this.nextElementSibling.classList.toggle('hidden')">
                                <td class="px-5 py-3 align-middle" onclick="event.stopPropagation()">
                                    @include('jobs-monitor::partials.checkbox', [
                                        'value' => $job['uuid'],
                                        'ariaLabel' => 'Select '.$job['short_class'],
                                        'attributes' => 'data-jm-bulk-row data-retryable="'.(($vm->retryEnabled && $job['has_payload']) ? '1' : '0').'"',
                                    ])
                                </td>
                                <td class="px-5 py-3 font-medium" title="{{ $job['job_class'] }}">{{ $job['short_class'] }}</td>
                                <td class="px-5 py-3 text-muted-foreground"><code class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">{{ $job['queue'] }}</code></td>
                                <td class="px-5 py-3 text-muted-foreground tabular-nums">{{ $job['attempt'] }}</td>
                                <td class="px-5 py-3">
                                    @include('jobs-monitor::partials.failure-category-badge', [
                                        'value' => $job['failure_category'],
                                        'label' => $job['failure_category_label'],
                                    ])
                                </td>
                                <td class="px-5 py-3 text-muted-foreground tabular-nums text-xs">{{ $job['finished_at'] ?? $job['started_at'] }}</td>
                                <td class="px-5 py-3 text-destructive truncate max-w-xs text-xs" title="{{ $job['exception'] ?? '' }}">{{ \Illuminate\Support\Str::limit($job['exception'] ?? '', 50) }}</td>
                                <td class="px-5 py-3 text-right" onclick="event.stopPropagation()">
                                    <div class="relative inline-block text-left" data-dlq-menu>
                                        <button type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-ring"
                                                title="Actions"
                                                onclick="toggleDlqMenu(this)">
                                            <i data-lucide="more-horizontal" class="text-[16px]"></i>
                                        </button>
                                        <div class="hidden absolute right-0 z-10 mt-1 w-52 origin-top-right rounded-lg bg-popover text-popover-foreground shadow-lg ring-1 ring-border focus:outline-none animate-slide-down"
                                             data-dlq-menu-dropdown>
                                            <div class="p-1">
                                                @if($vm->retryEnabled && $job['has_payload'])
                                                    <form method="POST" action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $job['uuid']]) }}" class="block">
                                                        @csrf
                                                        <button type="submit" class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground">
                                                            <i data-lucide="refresh-cw" class="text-[14px] text-brand"></i>
                                                            Retry
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('jobs-monitor.dlq.edit', ['uuid' => $job['uuid']]) }}"
                                                       class="flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground">
                                                        <i data-lucide="pencil" class="text-[14px] text-brand"></i>
                                                        Edit &amp; retry
                                                    </a>
                                                    <div class="h-px bg-border my-1"></div>
                                                @endif
                                                <button type="button"
                                                        class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm text-destructive rounded-md hover:bg-destructive/10"
                                                        onclick="openDlqDeleteConfirm('{{ $job['uuid'] }}', '{{ $job['short_class'] }}', '{{ $job['attempt'] }}')">
                                                    <i data-lucide="trash-2" class="text-[14px]"></i>
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hidden">
                                <td colspan="8" class="px-5 py-4 bg-muted/30 animate-slide-down">
                                    <div class="flex justify-end mb-3">
                                        <a href="{{ route('jobs-monitor.detail', ['uuid' => $job['uuid'], 'attempt' => $job['attempt']]) }}"
                                           class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors shadow-xs">
                                            View details &amp; retry timeline
                                            <i data-lucide="arrow-right" class="text-[13px]"></i>
                                        </a>
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
            </div>

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

    {{-- Delete confirmation modal --}}
    <div id="dlq-delete-modal"
         class="hidden fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="dlq-delete-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center px-4">
            <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity" onclick="closeDlqDeleteConfirm()"></div>

            <div class="relative w-full max-w-md transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border transition-all animate-slide-down">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20">
                            <i data-lucide="alert-triangle" class="text-[18px]"></i>
                        </div>
                        <div class="flex-1">
                            <h3 id="dlq-delete-title" class="text-base font-semibold">Delete dead-letter entry?</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                You're about to remove <span id="dlq-delete-job" class="font-mono text-foreground"></span>
                                and all <span id="dlq-delete-attempts" class="font-semibold text-foreground"></span> of its stored attempts.
                                This can't be undone.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-muted/40 px-6 py-3 flex justify-end gap-2 border-t border-border">
                    <button type="button"
                            class="inline-flex items-center h-9 px-4 text-sm font-medium rounded-md border border-border bg-card hover:bg-accent hover:text-accent-foreground transition-colors"
                            onclick="closeDlqDeleteConfirm()">Cancel</button>
                    <form id="dlq-delete-form" method="POST" action="">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-destructive text-destructive-foreground hover:bg-destructive/90 transition-colors shadow-xs">
                            <i data-lucide="trash-2" class="text-[14px]"></i>
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDlqMenu(button) {
            const dropdown = button.nextElementSibling;
            document.querySelectorAll('[data-dlq-menu-dropdown]').forEach(d => {
                if (d !== dropdown) d.classList.add('hidden');
            });
            dropdown.classList.toggle('hidden');
            if (window.__jmRefreshIcons) window.__jmRefreshIcons();
        }

        document.addEventListener('click', function (e) {
            document.querySelectorAll('[data-dlq-menu]').forEach(menu => {
                if (!menu.contains(e.target)) {
                    menu.querySelector('[data-dlq-menu-dropdown]')?.classList.add('hidden');
                }
            });
        });

        function openDlqDeleteConfirm(uuid, jobClass, attempts) {
            const modal = document.getElementById('dlq-delete-modal');
            const form = document.getElementById('dlq-delete-form');
            document.getElementById('dlq-delete-job').textContent = jobClass;
            document.getElementById('dlq-delete-attempts').textContent = attempts + (attempts === '1' ? ' attempt' : ' attempts');
            form.action = '{{ url(config('jobs-monitor.ui.path', 'jobs-monitor').'/dlq') }}/' + uuid + '/delete';
            modal.classList.remove('hidden');
            document.querySelectorAll('[data-dlq-menu-dropdown]').forEach(d => d.classList.add('hidden'));
            if (window.__jmRefreshIcons) window.__jmRefreshIcons();
        }

        function closeDlqDeleteConfirm() {
            document.getElementById('dlq-delete-modal').classList.add('hidden');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDlqDeleteConfirm();
        });
    </script>

    @include('jobs-monitor::partials.bulk-script')
@endsection
