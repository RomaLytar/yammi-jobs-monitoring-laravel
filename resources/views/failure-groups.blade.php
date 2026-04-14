@extends('jobs-monitor::layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="fingerprint" class="text-[18px]"></i>
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Failure groups</h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Failures grouped by normalized trace signature. One row per unique problem.
                </p>
            </div>
        </div>

        @if($vm->total > 0)
            <button type="button"
                    id="jm-failures-retry-all"
                    class="inline-flex items-center gap-1.5 h-9 px-3 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs"
                    title="Re-dispatch every job in every group">
                <i data-lucide="refresh-cw" class="text-[14px]"></i>
                Retry all groups
            </button>
        @endif
    </div>

    <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <i data-lucide="layers" class="text-[16px]"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold">
                        {{ number_format($vm->total) }} {{ $vm->total === 1 ? 'group' : 'groups' }}
                    </h2>
                    <p class="text-xs text-muted-foreground">Sorted by last seen</p>
                </div>
            </div>
            @if($vm->total > 0)
                <button type="button"
                        class="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-medium rounded-md border border-border bg-card hover:bg-accent transition-colors"
                        data-jm-bulk-select-all="failures"
                        title="Select every failure group across all pages">
                    <i data-lucide="check-check" class="text-[14px]"></i>
                    Select all {{ number_format($vm->total) }} matching
                </button>
            @endif
        </div>

        @if($vm->total === 0)
            <div class="px-5 py-16 text-center">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success mb-3">
                    <i data-lucide="shield-check" class="text-xl"></i>
                </div>
                <p class="text-sm font-medium">No failure groups yet</p>
                <p class="text-xs text-muted-foreground mt-1">Grouping starts as soon as a job fails.</p>
            </div>
        @else
            <table class="w-full text-sm table-auto"
                   data-jm-bulk-scope="failures"
                   data-jm-bulk-candidates="{{ route('jobs-monitor.failures.groups.bulk.candidates') }}"
                   data-jm-bulk-retry="{{ route('jobs-monitor.failures.groups.bulk.retry') }}"
                   data-jm-bulk-delete="{{ route('jobs-monitor.failures.groups.bulk.delete') }}"
                   data-jm-bulk-noun="group">
                <thead>
                    <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                        <th class="w-10 px-5 py-2.5">
                            @include('jobs-monitor::partials.checkbox', [
                                'ariaLabel' => 'Select all on page',
                                'attributes' => 'data-jm-bulk-page-select',
                            ])
                        </th>
                        <th class="text-left font-medium px-5 py-2.5">Fingerprint</th>
                        <th class="text-left font-medium px-5 py-2.5">Exception</th>
                        <th class="text-left font-medium px-5 py-2.5">Message</th>
                        <th class="text-right font-medium px-5 py-2.5">Occurrences</th>
                        <th class="text-left font-medium px-5 py-2.5">Classes</th>
                        <th class="text-left font-medium px-5 py-2.5">Last seen</th>
                        <th class="text-right font-medium px-5 py-2.5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($vm->groups as $g)
                        <tr class="{{ $loop->even ? 'bg-muted/40' : 'bg-card' }} hover:bg-muted/60 transition-colors">
                            <td class="px-5 py-3 align-middle">
                                @include('jobs-monitor::partials.checkbox', [
                                    'value' => $g['fingerprint'],
                                    'ariaLabel' => 'Select '.$g['sample_exception_short'],
                                    'attributes' => 'data-jm-bulk-row data-retryable="1"',
                                ])
                            </td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $g['fingerprint'] }}</td>
                            <td class="px-5 py-3 text-xs" title="{{ $g['sample_exception_class'] }}">{{ $g['sample_exception_short'] }}</td>
                            <td class="px-5 py-3 text-xs text-muted-foreground max-w-xs truncate" title="{{ $g['sample_message'] }}">
                                {{ \Illuminate\Support\Str::limit($g['sample_message'], 60) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold">{{ number_format($g['occurrences']) }}</td>
                            <td class="px-5 py-3 text-xs">
                                @php $classes = $g['affected_job_classes']; $shortFirst = \Illuminate\Support\Str::afterLast($classes[0] ?? '', '\\'); @endphp
                                <span title="{{ $classes[0] ?? '' }}">{{ $shortFirst }}</span>
                                @if(count($classes) > 1)
                                    <span class="text-muted-foreground" title="{{ implode(', ', $classes) }}">+{{ count($classes) - 1 }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-muted-foreground tabular-nums">{{ $g['last_seen_at'] }}</td>
                            <td class="px-5 py-3 text-right" onclick="event.stopPropagation()">
                                <div class="relative inline-block text-left" data-fg-menu>
                                    <button type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-ring"
                                            title="Actions"
                                            onclick="toggleFgMenu(this)">
                                        <i data-lucide="more-horizontal" class="text-[16px]"></i>
                                    </button>
                                    <div class="hidden absolute right-0 z-10 mt-1 w-44 origin-top-right rounded-lg bg-popover text-popover-foreground shadow-lg ring-1 ring-border focus:outline-none animate-slide-down"
                                         data-fg-menu-dropdown>
                                        <div class="p-1">
                                            <button type="button"
                                                    class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground"
                                                    onclick="openFgConfirm('retry', '{{ $g['fingerprint'] }}', '{{ $g['sample_exception_short'] }}', {{ (int) $g['occurrences'] }})">
                                                <i data-lucide="refresh-cw" class="text-[14px] text-brand"></i>
                                                Retry group
                                            </button>
                                            <div class="h-px bg-border my-1"></div>
                                            <button type="button"
                                                    class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm text-destructive rounded-md hover:bg-destructive/10"
                                                    onclick="openFgConfirm('delete', '{{ $g['fingerprint'] }}', '{{ $g['sample_exception_short'] }}', {{ (int) $g['occurrences'] }})">
                                                <i data-lucide="trash-2" class="text-[14px]"></i>
                                                Delete group
                                            </button>
                                        </div>
                                    </div>
                                </div>
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
                    'routeName' => 'jobs-monitor.failures.groups.page',
                ])
            @endif
        @endif
    </div>

    @include('jobs-monitor::partials.bulk-bar', [
        'scope' => 'failures',
        'retryEnabled' => true,
        'showDelete' => true,
        'noun' => 'group',
    ])

    @include('jobs-monitor::partials.bulk-script')

    {{-- Single-group confirm modal (retry / delete) --}}
    <div id="fg-confirm-modal"
         class="hidden fixed inset-0 z-50 overflow-y-auto"
         role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center px-4">
            <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity" onclick="closeFgConfirm()"></div>
            <div class="relative w-full max-w-md transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border transition-all animate-slide-down">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div id="fg-confirm-icon-wrap" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full">
                            <i data-lucide="refresh-cw" class="text-[18px]" id="fg-confirm-icon"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 id="fg-confirm-title" class="text-base font-semibold">Confirm</h3>
                            <p id="fg-confirm-body" class="mt-2 text-sm text-muted-foreground"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-muted/40 px-6 py-3 flex justify-end gap-2 border-t border-border">
                    <button type="button"
                            class="inline-flex items-center h-9 px-4 text-sm font-medium rounded-md border border-border bg-card hover:bg-accent hover:text-accent-foreground transition-colors"
                            onclick="closeFgConfirm()">Cancel</button>
                    <form id="fg-confirm-form" method="POST" action="">
                        @csrf
                        <button type="submit" id="fg-confirm-submit"
                                class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md transition-colors shadow-xs">
                            <i data-lucide="check" class="text-[14px]"></i>
                            <span id="fg-confirm-submit-label">Confirm</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Retry-all confirm modal --}}
    <div id="fg-retry-all-modal"
         class="hidden fixed inset-0 z-50 overflow-y-auto"
         role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center px-4">
            <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity" onclick="closeFgRetryAll()"></div>
            <div class="relative w-full max-w-md transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border transition-all animate-slide-down">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary ring-1 ring-inset ring-primary/20">
                            <i data-lucide="refresh-cw" class="text-[18px]"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-semibold">Retry every failing group?</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Re-dispatches every job across <span id="fg-retry-all-count" class="font-semibold text-foreground">all</span>
                                failure groups. Jobs that still hit the same bug will fail again and the group's occurrences will grow.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-muted/40 px-6 py-3 flex justify-end gap-2 border-t border-border">
                    <button type="button"
                            class="inline-flex items-center h-9 px-4 text-sm font-medium rounded-md border border-border bg-card hover:bg-accent hover:text-accent-foreground transition-colors"
                            onclick="closeFgRetryAll()">Cancel</button>
                    <button type="button" id="fg-retry-all-submit"
                            class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs">
                        <i data-lucide="refresh-cw" class="text-[14px]"></i>
                        Retry all
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Kebab menu toggle (one open at a time, click-outside to close).
        function toggleFgMenu(button) {
            var dd = button.nextElementSibling;
            if (! dd) return;
            document.querySelectorAll('[data-fg-menu-dropdown]').forEach(function (el) {
                if (el !== dd) el.classList.add('hidden');
            });
            dd.classList.toggle('hidden');
        }
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-fg-menu]')) return;
            document.querySelectorAll('[data-fg-menu-dropdown]').forEach(function (el) {
                el.classList.add('hidden');
            });
        });

        // Single-group confirm modal (retry / delete from kebab).
        var FG_RETRY_URL  = @json(route('jobs-monitor.failures.groups.retry', ['fingerprint' => '__FP__']));
        var FG_DELETE_URL = @json(route('jobs-monitor.failures.groups.delete', ['fingerprint' => '__FP__']));

        function openFgConfirm(action, fingerprint, exceptionShort, occurrences) {
            var modal      = document.getElementById('fg-confirm-modal');
            var title      = document.getElementById('fg-confirm-title');
            var body       = document.getElementById('fg-confirm-body');
            var iconWrap   = document.getElementById('fg-confirm-icon-wrap');
            var icon       = document.getElementById('fg-confirm-icon');
            var submit     = document.getElementById('fg-confirm-submit');
            var submitText = document.getElementById('fg-confirm-submit-label');
            var form       = document.getElementById('fg-confirm-form');

            var isRetry = action === 'retry';
            form.action = (isRetry ? FG_RETRY_URL : FG_DELETE_URL).replace('__FP__', fingerprint);
            title.textContent = isRetry ? 'Retry this group?' : 'Delete this group?';
            body.innerHTML = isRetry
                ? 'Re-dispatches every job in <span class="font-mono text-foreground">' + fingerprint + '</span> ' +
                  '(' + exceptionShort + ', ' + occurrences + ' occurrence' + (occurrences === 1 ? '' : 's') + '). ' +
                  'Jobs that still hit the same bug will fail again.'
                : 'Permanently removes every job row in group <span class="font-mono text-foreground">' + fingerprint + '</span>. ' +
                  'The group entry stays so the fingerprint history is preserved.';

            iconWrap.className = 'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full ' +
                (isRetry ? 'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20'
                         : 'bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20');
            iconWrap.innerHTML = '<i data-lucide="' + (isRetry ? 'refresh-cw' : 'trash-2') + '" class="text-[18px]"></i>';

            submit.className = 'inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md transition-colors shadow-xs ' +
                (isRetry ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                         : 'bg-destructive text-destructive-foreground hover:bg-destructive/90');
            submit.innerHTML = '<i data-lucide="' + (isRetry ? 'refresh-cw' : 'trash-2') + '" class="text-[14px]"></i> ' +
                '<span>' + (isRetry ? 'Retry' : 'Delete') + '</span>';

            modal.classList.remove('hidden');
            if (window.lucide) window.lucide.createIcons();
        }

        function closeFgConfirm() {
            document.getElementById('fg-confirm-modal').classList.add('hidden');
        }

        // "Retry all groups" — opens its own modal, then runs the bulk endpoint.
        (function () {
            var btn = document.getElementById('jm-failures-retry-all');
            if (! btn) return;
            var modal         = document.getElementById('fg-retry-all-modal');
            var submitBtn     = document.getElementById('fg-retry-all-submit');
            var totalSpan     = document.getElementById('fg-retry-all-count');
            var candidatesUrl = @json(route('jobs-monitor.failures.groups.bulk.candidates'));
            var retryUrl      = @json(route('jobs-monitor.failures.groups.bulk.retry'));
            var csrf          = @json(csrf_token());

            btn.addEventListener('click', function () {
                totalSpan.textContent = @json($vm->total).toLocaleString();
                modal.classList.remove('hidden');
            });

            submitBtn.addEventListener('click', async function () {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i data-lucide="loader" class="text-[14px] animate-spin"></i> Sending…';
                if (window.lucide) window.lucide.createIcons();
                try {
                    var res = await fetch(candidatesUrl, { headers: { 'Accept': 'application/json' } });
                    var json = await res.json();
                    var ids = json.ids || [];
                    if (ids.length === 0) { window.location.reload(); return; }

                    var post = await fetch(retryUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ ids: ids }),
                    });
                    var result = await post.json();
                    window.location.reload();
                } catch (e) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i data-lucide="refresh-cw" class="text-[14px]"></i> Retry all';
                    if (window.lucide) window.lucide.createIcons();
                }
            });
        })();

        function closeFgRetryAll() {
            document.getElementById('fg-retry-all-modal').classList.add('hidden');
        }
    </script>
@endsection
