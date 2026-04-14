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
    </div>

    <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <i data-lucide="layers" class="text-[16px]"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold" data-jm-group-count>—</h2>
                    <p class="text-xs text-muted-foreground">Sorted by last seen</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-muted-foreground text-xs uppercase tracking-wider">
                    <tr>
                        <th class="text-left font-medium px-5 py-2.5">Fingerprint</th>
                        <th class="text-left font-medium px-5 py-2.5">Exception</th>
                        <th class="text-left font-medium px-5 py-2.5">Message</th>
                        <th class="text-right font-medium px-5 py-2.5">Occurrences</th>
                        <th class="text-left font-medium px-5 py-2.5">Classes</th>
                        <th class="text-left font-medium px-5 py-2.5">Last seen</th>
                        <th class="text-right font-medium px-5 py-2.5">Actions</th>
                    </tr>
                </thead>
                <tbody data-jm-groups-body>
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-sm text-muted-foreground">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function () {
            const indexUrl   = @json(route('jobs-monitor.failures.groups.index'));
            const retryUrl   = (fp) => @json(url('/').'/jobs-monitor/failures/groups/') + fp + '/retry';
            const deleteUrl  = (fp) => @json(url('/').'/jobs-monitor/failures/groups/') + fp + '/delete';
            const csrfToken  = @json(csrf_token());

            const body     = document.querySelector('[data-jm-groups-body]');
            const counter  = document.querySelector('[data-jm-group-count]');

            function escape(s) {
                return String(s ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;');
            }

            function row(g) {
                const classes = g.affected_job_classes.slice(0, 2).map(escape).join(', ');
                const more    = g.affected_job_classes.length > 2
                    ? ' <span class="text-muted-foreground">+' + (g.affected_job_classes.length - 2) + '</span>'
                    : '';
                return `
                    <tr class="border-t border-border hover:bg-accent/40 transition-colors">
                        <td class="px-5 py-3 font-mono text-xs">${escape(g.fingerprint)}</td>
                        <td class="px-5 py-3 text-xs">${escape(g.sample_exception_class)}</td>
                        <td class="px-5 py-3 text-xs text-muted-foreground max-w-xs truncate" title="${escape(g.sample_message)}">${escape(g.sample_message)}</td>
                        <td class="px-5 py-3 text-right tabular-nums">${g.occurrences}</td>
                        <td class="px-5 py-3 text-xs">${classes}${more}</td>
                        <td class="px-5 py-3 text-xs text-muted-foreground tabular-nums">${escape(g.last_seen_at)}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button type="button" data-jm-retry="${escape(g.fingerprint)}"
                                    class="inline-flex items-center gap-1 h-7 px-2 text-xs rounded-md border border-border bg-card hover:bg-accent transition-colors">
                                <i data-lucide="refresh-cw" class="text-[12px]"></i> Retry
                            </button>
                            <button type="button" data-jm-delete="${escape(g.fingerprint)}"
                                    class="inline-flex items-center gap-1 h-7 px-2 text-xs rounded-md border border-destructive/25 text-destructive hover:bg-destructive/10 transition-colors ml-1">
                                <i data-lucide="trash-2" class="text-[12px]"></i> Delete
                            </button>
                        </td>
                    </tr>
                `;
            }

            function emptyRow() {
                return `
                    <tr>
                        <td colspan="7" class="px-5 py-16 text-center">
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success mb-3">
                                <i data-lucide="shield-check" class="text-xl"></i>
                            </div>
                            <p class="text-sm font-medium">No failure groups yet</p>
                            <p class="text-xs text-muted-foreground mt-1">Grouping starts as soon as a job fails.</p>
                        </td>
                    </tr>
                `;
            }

            async function load() {
                try {
                    const res = await fetch(indexUrl, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    const data = json.data ?? [];
                    counter.textContent = (json.meta?.total ?? data.length) + ' group' + (data.length === 1 ? '' : 's');
                    body.innerHTML = data.length === 0 ? emptyRow() : data.map(row).join('');
                    if (window.lucide) window.lucide.createIcons();
                } catch (e) {
                    body.innerHTML = '<tr><td colspan="7" class="px-5 py-10 text-center text-sm text-destructive">Failed to load groups.</td></tr>';
                }
            }

            async function post(url) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
            }

            body.addEventListener('click', async (ev) => {
                const retryBtn  = ev.target.closest('[data-jm-retry]');
                const deleteBtn = ev.target.closest('[data-jm-delete]');

                if (retryBtn) {
                    retryBtn.disabled = true;
                    await post(retryUrl(retryBtn.dataset.jmRetry));
                    await load();
                }
                if (deleteBtn) {
                    if (!confirm('Delete all jobs in this group?')) return;
                    deleteBtn.disabled = true;
                    await post(deleteUrl(deleteBtn.dataset.jmDelete));
                    await load();
                }
            });

            load();
        })();
    </script>
@endsection
