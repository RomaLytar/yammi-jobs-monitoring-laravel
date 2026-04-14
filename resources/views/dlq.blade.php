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
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
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
                <table class="w-full text-sm" data-dlq-table>
                    <thead>
                        <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                            <th class="w-10 px-5 py-2.5">
                                @include('jobs-monitor::partials.checkbox', [
                                    'ariaLabel' => 'Select all on page',
                                    'attributes' => 'data-dlq-select-all',
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
                                        'attributes' => 'data-dlq-row-select data-retryable="'.(($vm->retryEnabled && $job['has_payload']) ? '1' : '0').'"',
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

    {{-- Sticky bulk action bar (visible when >=1 row is selected) --}}
    <div id="dlq-bulk-bar"
         class="hidden fixed inset-x-0 bottom-4 z-40 pointer-events-none px-4">
        <div class="pointer-events-auto mx-auto max-w-3xl flex items-center justify-between gap-3 rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border px-4 py-3">
            <div class="flex items-center gap-2 text-sm">
                <i data-lucide="check-square" class="text-[16px] text-primary"></i>
                <span><span data-dlq-bulk-count>0</span> selected</span>
            </div>
            <div class="flex items-center gap-2">
                @if($vm->retryEnabled)
                    <button type="button"
                            class="inline-flex items-center gap-1.5 h-9 px-3 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs"
                            data-dlq-bulk-retry>
                        <i data-lucide="refresh-cw" class="text-[14px]"></i>
                        Retry selected
                    </button>
                @endif
                <button type="button"
                        class="inline-flex items-center gap-1.5 h-9 px-3 text-sm font-semibold rounded-md bg-destructive text-destructive-foreground hover:bg-destructive/90 transition-colors shadow-xs"
                        data-dlq-bulk-delete>
                    <i data-lucide="trash-2" class="text-[14px]"></i>
                    Delete selected
                </button>
                <button type="button"
                        class="inline-flex items-center h-9 px-3 text-sm font-medium rounded-md border border-border bg-card hover:bg-accent transition-colors"
                        data-dlq-bulk-clear>Clear</button>
            </div>
        </div>
    </div>

    {{-- Bulk progress / result modal --}}
    <div id="dlq-bulk-modal"
         class="hidden fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="dlq-bulk-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center px-4">
            <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity" data-dlq-bulk-backdrop></div>
            <div class="relative w-full max-w-lg transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border animate-slide-down">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full" data-dlq-bulk-icon-wrap>
                            <i data-lucide="refresh-cw" class="text-[18px]" data-dlq-bulk-icon></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 id="dlq-bulk-title" class="text-base font-semibold" data-dlq-bulk-title>Confirm bulk action</h3>
                            <p class="mt-2 text-sm text-muted-foreground" data-dlq-bulk-message></p>
                            <div class="hidden mt-3 w-full h-2 rounded-full bg-muted overflow-hidden" data-dlq-bulk-progress-wrap>
                                <div class="h-2 bg-primary transition-all" style="width: 0" data-dlq-bulk-progress-bar></div>
                            </div>
                            <div class="hidden mt-3 max-h-48 overflow-y-auto rounded-lg border border-border bg-card text-xs" data-dlq-bulk-errors-wrap>
                                <div class="px-3 py-2 border-b border-border font-medium text-destructive">Errors</div>
                                <ul class="divide-y divide-border" data-dlq-bulk-errors></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-muted/40 px-6 py-3 flex justify-end gap-2 border-t border-border" data-dlq-bulk-footer>
                    <button type="button"
                            class="inline-flex items-center h-9 px-4 text-sm font-medium rounded-md border border-border bg-card hover:bg-accent transition-colors"
                            data-dlq-bulk-cancel>Cancel</button>
                    <button type="button"
                            class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs"
                            data-dlq-bulk-confirm>Confirm</button>
                </div>
            </div>
        </div>
    </div>

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

        // ---------------- Bulk selection + chunked dispatch ----------------
        (function () {
            const CHUNK = 100;
            const retryUrl = '{{ route('jobs-monitor.dlq.bulk.retry') }}';
            const deleteUrl = '{{ route('jobs-monitor.dlq.bulk.delete') }}';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                ?? document.querySelector('input[name="_token"]')?.value
                ?? '';

            const table = document.querySelector('[data-dlq-table]');
            if (! table) return;

            const rowBoxes = () => Array.from(table.querySelectorAll('[data-dlq-row-select]'));
            const selectAll = table.querySelector('[data-dlq-select-all]');
            const bar = document.getElementById('dlq-bulk-bar');
            const barCount = bar?.querySelector('[data-dlq-bulk-count]');

            const selected = () => rowBoxes().filter(cb => cb.checked).map(cb => cb.value);

            function refreshBar() {
                const n = selected().length;
                if (barCount) barCount.textContent = String(n);
                if (bar) bar.classList.toggle('hidden', n === 0);
                if (selectAll) {
                    const all = rowBoxes();
                    selectAll.checked = all.length > 0 && all.every(cb => cb.checked);
                    selectAll.indeterminate = ! selectAll.checked && all.some(cb => cb.checked);
                }
            }

            rowBoxes().forEach(cb => cb.addEventListener('change', refreshBar));
            selectAll?.addEventListener('change', () => {
                rowBoxes().forEach(cb => { cb.checked = selectAll.checked; });
                refreshBar();
            });
            bar?.querySelector('[data-dlq-bulk-clear]')?.addEventListener('click', () => {
                rowBoxes().forEach(cb => { cb.checked = false; });
                refreshBar();
            });

            // -------- Modal state machine --------
            const modal = document.getElementById('dlq-bulk-modal');
            const titleEl = modal.querySelector('[data-dlq-bulk-title]');
            const messageEl = modal.querySelector('[data-dlq-bulk-message]');
            const iconEl = modal.querySelector('[data-dlq-bulk-icon]');
            const iconWrap = modal.querySelector('[data-dlq-bulk-icon-wrap]');
            const progressWrap = modal.querySelector('[data-dlq-bulk-progress-wrap]');
            const progressBar = modal.querySelector('[data-dlq-bulk-progress-bar]');
            const errorsWrap = modal.querySelector('[data-dlq-bulk-errors-wrap]');
            const errorsList = modal.querySelector('[data-dlq-bulk-errors]');
            const footer = modal.querySelector('[data-dlq-bulk-footer]');
            const confirmBtn = modal.querySelector('[data-dlq-bulk-confirm]');
            const cancelBtn = modal.querySelector('[data-dlq-bulk-cancel]');

            function openModal() {
                modal.classList.remove('hidden');
                if (window.__jmRefreshIcons) window.__jmRefreshIcons();
            }
            function closeModal() {
                modal.classList.add('hidden');
                errorsWrap.classList.add('hidden');
                progressWrap.classList.add('hidden');
                errorsList.innerHTML = '';
            }
            modal.querySelector('[data-dlq-bulk-backdrop]').addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

            function setIcon(name, color) {
                iconEl.setAttribute('data-lucide', name);
                iconWrap.className = 'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full ' + color;
                if (window.__jmRefreshIcons) window.__jmRefreshIcons();
            }

            async function runChunked(url, ids, labelNoun, labelVerb, verbPast) {
                // Configure modal: progress mode
                titleEl.textContent = verbPast + ' ' + ids.length + ' ' + (ids.length === 1 ? labelNoun : labelNoun + 's');
                messageEl.textContent = 'Processing in chunks of ' + CHUNK + '. Don\u2019t close this tab.';
                setIcon('loader-2', 'bg-primary/10 text-primary');
                progressWrap.classList.remove('hidden');
                progressBar.style.width = '0%';
                errorsWrap.classList.add('hidden');
                errorsList.innerHTML = '';
                footer.classList.add('hidden');
                openModal();

                let succeeded = 0;
                let failed = 0;
                const allErrors = {};

                for (let i = 0; i < ids.length; i += CHUNK) {
                    const chunk = ids.slice(i, i + CHUNK);
                    try {
                        const resp = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ ids: chunk }),
                            credentials: 'same-origin',
                        });

                        if (! resp.ok) {
                            failed += chunk.length;
                            chunk.forEach(id => { allErrors[id] = 'HTTP ' + resp.status; });
                        } else {
                            const data = await resp.json();
                            succeeded += data.succeeded ?? 0;
                            failed += data.failed ?? 0;
                            if (data.errors && typeof data.errors === 'object') {
                                Object.assign(allErrors, data.errors);
                            }
                        }
                    } catch (err) {
                        failed += chunk.length;
                        chunk.forEach(id => { allErrors[id] = String(err); });
                    }

                    const done = Math.min(i + CHUNK, ids.length);
                    progressBar.style.width = ((done / ids.length) * 100).toFixed(1) + '%';
                    messageEl.textContent = labelVerb + ' ' + done + ' / ' + ids.length
                        + ' \u00b7 ' + succeeded + ' succeeded, ' + failed + ' failed';
                }

                // Summary mode
                const allOk = failed === 0;
                setIcon(allOk ? 'check-circle-2' : 'alert-triangle',
                    allOk ? 'bg-success/10 text-success ring-1 ring-inset ring-success/20'
                          : 'bg-warning/10 text-warning ring-1 ring-inset ring-warning/20');
                titleEl.textContent = verbPast + ' ' + succeeded + ' / ' + ids.length;
                messageEl.textContent = failed > 0
                    ? failed + ' ' + (failed === 1 ? 'item' : 'items') + ' could not be ' + verbPast.toLowerCase() + '. See details below.'
                    : 'All selected ' + labelNoun + 's were ' + verbPast.toLowerCase() + ' successfully.';

                if (Object.keys(allErrors).length > 0) {
                    errorsList.innerHTML = Object.entries(allErrors).map(([id, msg]) =>
                        '<li class="px-3 py-2"><code class="font-mono text-[11px] text-muted-foreground">'
                        + escapeHtml(id) + '</code><div class="text-foreground mt-0.5">'
                        + escapeHtml(msg) + '</div></li>'
                    ).join('');
                    errorsWrap.classList.remove('hidden');
                }

                // Replace footer with a single "Done" that reloads the page
                footer.classList.remove('hidden');
                cancelBtn.classList.add('hidden');
                confirmBtn.textContent = 'Reload';
                confirmBtn.onclick = () => window.location.reload();
            }

            function escapeHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function askConfirm({ title, body, icon, iconColor, confirmLabel, confirmColor, onConfirm }) {
                progressWrap.classList.add('hidden');
                errorsWrap.classList.add('hidden');
                footer.classList.remove('hidden');
                cancelBtn.classList.remove('hidden');
                titleEl.textContent = title;
                messageEl.textContent = body;
                setIcon(icon, iconColor);
                confirmBtn.textContent = confirmLabel;
                confirmBtn.className = 'inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md '
                    + confirmColor + ' transition-colors shadow-xs';
                confirmBtn.onclick = () => { onConfirm(); };
                openModal();
            }

            bar?.querySelector('[data-dlq-bulk-retry]')?.addEventListener('click', () => {
                const allIds = selected();
                const nonRetryable = rowBoxes().filter(cb => cb.checked && cb.dataset.retryable !== '1').map(cb => cb.value);
                const ids = allIds.filter(id => ! nonRetryable.includes(id));
                if (ids.length === 0) {
                    alert('None of the selected jobs can be retried (payload not stored).');
                    return;
                }
                const skippedNote = nonRetryable.length > 0
                    ? ' ' + nonRetryable.length + ' selected ' + (nonRetryable.length === 1 ? 'entry has' : 'entries have') + ' no stored payload and will be skipped.'
                    : '';
                askConfirm({
                    title: 'Retry ' + ids.length + ' ' + (ids.length === 1 ? 'job' : 'jobs') + '?',
                    body: 'The selected jobs will be re-dispatched on their original queues.' + skippedNote,
                    icon: 'refresh-cw',
                    iconColor: 'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20',
                    confirmLabel: 'Retry ' + ids.length,
                    confirmColor: 'bg-primary text-primary-foreground hover:bg-primary/90',
                    onConfirm: () => runChunked(retryUrl, ids, 'job', 'Retrying', 'Retried'),
                });
            });

            bar?.querySelector('[data-dlq-bulk-delete]')?.addEventListener('click', () => {
                const ids = selected();
                if (ids.length === 0) return;
                askConfirm({
                    title: 'Delete ' + ids.length + ' dead-letter ' + (ids.length === 1 ? 'entry' : 'entries') + '?',
                    body: 'Every stored attempt for the selected ' + (ids.length === 1 ? 'UUID' : 'UUIDs') + ' will be permanently removed. This cannot be undone.',
                    icon: 'alert-triangle',
                    iconColor: 'bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20',
                    confirmLabel: 'Delete ' + ids.length,
                    confirmColor: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                    onConfirm: () => runChunked(deleteUrl, ids, 'entry', 'Deleting', 'Deleted'),
                });
            });

            refreshBar();
        })();
    </script>
@endsection
