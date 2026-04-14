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
                                            <form method="POST"
                                                  action="{{ route('jobs-monitor.failures.groups.retry', ['fingerprint' => $g['fingerprint']]) }}"
                                                  class="block">
                                                @csrf
                                                <button type="submit"
                                                        class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground">
                                                    <i data-lucide="refresh-cw" class="text-[14px] text-brand"></i>
                                                    Retry group
                                                </button>
                                            </form>
                                            <div class="h-px bg-border my-1"></div>
                                            <form method="POST"
                                                  action="{{ route('jobs-monitor.failures.groups.delete', ['fingerprint' => $g['fingerprint']]) }}"
                                                  class="block"
                                                  onsubmit="return confirm('Delete all jobs in this group?')">
                                                @csrf
                                                <button type="submit"
                                                        class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm text-destructive rounded-md hover:bg-destructive/10">
                                                    <i data-lucide="trash-2" class="text-[14px]"></i>
                                                    Delete group
                                                </button>
                                            </form>
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

        // "Retry all groups" — uses the bulk endpoint with every fingerprint.
        (function () {
            var btn = document.getElementById('jm-failures-retry-all');
            if (! btn) return;
            var candidatesUrl = @json(route('jobs-monitor.failures.groups.bulk.candidates'));
            var retryUrl      = @json(route('jobs-monitor.failures.groups.bulk.retry'));
            var csrf          = @json(csrf_token());

            btn.addEventListener('click', async function () {
                if (! confirm('Retry every job across all failure groups?')) return;
                btn.disabled = true;
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
                    alert('Retry submitted: ' + (result.succeeded ?? 0) + ' ok, ' + (result.failed ?? 0) + ' failed.');
                    window.location.reload();
                } catch (e) {
                    alert('Retry failed: ' + e);
                    btn.disabled = false;
                }
            });
        })();
    </script>
@endsection
