{{--
    Shared bulk-selection controller. Loaded once per page that uses bulk.
    Each scope (e.g. 'dlq', 'failures') initialises its own instance via
    data attributes on the surrounding table:

      <table data-jm-bulk-scope="dlq"
             data-jm-bulk-candidates="/dlq/bulk/candidates"
             data-jm-bulk-retry="/dlq/bulk/retry"
             data-jm-bulk-delete="/dlq/bulk/delete"
             data-jm-bulk-noun="entry">
        <thead><tr>
          <th><input type="checkbox" data-jm-bulk-page-select></th>
          ...
        </tr></thead>
        <tbody><tr>
          <td><input type="checkbox" data-jm-bulk-row value="<uuid>" data-retryable="1"></td>
          ...
        </tr></tbody>
      </table>

    The bar + modal are rendered separately via partials.bulk-bar with the
    same scope id; this script wires DOM-checkboxes ↔ a Set<uuid> so the
    selection can span pagination.
--}}
<script>
(function () {
    if (window.__JmBulkController) return;

    const MAX = {{ (int) config('jobs-monitor.bulk.max_ids_per_request', 100) }};

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    class JmBulkController {
        constructor(table) {
            this.table = table;
            this.scope = table.dataset.jmBulkScope;
            this.candidatesUrl = table.dataset.jmBulkCandidates;
            this.retryUrl = table.dataset.jmBulkRetry;
            this.deleteUrl = table.dataset.jmBulkDelete || null;
            this.noun = table.dataset.jmBulkNoun || 'job';
            this.csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            this.selection = new Set();
            this.bar = document.getElementById('jm-bulk-bar-' + this.scope);
            this.modal = document.getElementById('jm-bulk-modal-' + this.scope);

            if (! this.bar || ! this.modal) {
                console.warn('jm-bulk: bar or modal missing for scope', this.scope);
                return;
            }

            this.bindRows();
            this.bindBar();
            this.bindModal();
            this.refreshUi();
        }

        rowBoxes() {
            return Array.from(this.table.querySelectorAll('[data-jm-bulk-row]'));
        }

        bindRows() {
            this.rowBoxes().forEach(cb => {
                if (this.selection.has(cb.value)) cb.checked = true;
                cb.addEventListener('change', () => {
                    if (cb.checked) this.selection.add(cb.value);
                    else this.selection.delete(cb.value);
                    this.refreshUi();
                });
            });

            const pageSelect = this.table.querySelector('[data-jm-bulk-page-select]');
            pageSelect?.addEventListener('change', () => {
                this.rowBoxes().forEach(cb => {
                    cb.checked = pageSelect.checked;
                    if (pageSelect.checked) this.selection.add(cb.value);
                    else this.selection.delete(cb.value);
                });
                this.refreshUi();
            });

            // "Select all matching" — one or more buttons outside the table
            document.querySelectorAll('[data-jm-bulk-select-all="' + this.scope + '"]').forEach(btn => {
                btn.addEventListener('click', () => this.selectAllMatching());
            });
        }

        bindBar() {
            this.bar.querySelector('[data-jm-bulk-clear]')?.addEventListener('click', () => this.clear());
            this.bar.querySelector('[data-jm-bulk-retry]')?.addEventListener('click', () => this.confirmRetry());
            this.bar.querySelector('[data-jm-bulk-delete]')?.addEventListener('click', () => this.confirmDelete());
        }

        bindModal() {
            this.modal.querySelector('[data-jm-bulk-backdrop]').addEventListener('click', () => this.closeModal());
            this.modal.querySelector('[data-jm-bulk-cancel]').addEventListener('click', () => this.closeModal());
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') this.closeModal(); });
        }

        openModal() {
            this.modal.classList.remove('hidden');
            if (window.__jmRefreshIcons) window.__jmRefreshIcons();
        }

        closeModal() {
            this.modal.classList.add('hidden');
            this.modal.querySelector('[data-jm-bulk-errors-wrap]').classList.add('hidden');
            this.modal.querySelector('[data-jm-bulk-progress-wrap]').classList.add('hidden');
            this.modal.querySelector('[data-jm-bulk-errors]').innerHTML = '';
        }

        clear() {
            this.selection.clear();
            this.rowBoxes().forEach(cb => cb.checked = false);
            this.refreshUi();
        }

        refreshUi() {
            const n = this.selection.size;
            const countEl = this.bar.querySelector('[data-jm-bulk-count]');
            if (countEl) countEl.textContent = String(n);
            this.bar.classList.toggle('hidden', n === 0);

            const pageSelect = this.table.querySelector('[data-jm-bulk-page-select]');
            if (pageSelect) {
                const all = this.rowBoxes();
                pageSelect.checked = all.length > 0 && all.every(cb => cb.checked);
                pageSelect.indeterminate = ! pageSelect.checked && all.some(cb => cb.checked);
            }

            // Reflect global state in each "select-all-matching" label
            document.querySelectorAll('[data-jm-bulk-selected-total="' + this.scope + '"]').forEach(el => {
                el.textContent = String(n);
            });
        }

        async selectAllMatching() {
            try {
                const resp = await fetch(this.candidatesUrl, {
                    method: 'GET',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    credentials: 'same-origin',
                });
                if (! resp.ok) { alert('Failed to fetch candidates: HTTP ' + resp.status); return; }
                const data = await resp.json();
                (data.ids || []).forEach(id => this.selection.add(id));
                this.rowBoxes().forEach(cb => { if (this.selection.has(cb.value)) cb.checked = true; });

                const truncEl = this.bar.querySelector('[data-jm-bulk-truncated]');
                if (truncEl) {
                    if (data.truncated) {
                        truncEl.textContent = '(' + data.total + ' total, capped at ' + data.ids.length + ' — narrow the filter for the rest)';
                        truncEl.classList.remove('hidden');
                    } else {
                        truncEl.textContent = '';
                        truncEl.classList.add('hidden');
                    }
                }
                this.refreshUi();
            } catch (err) {
                alert('Failed to fetch candidates: ' + err);
            }
        }

        confirmRetry() {
            if (this.selection.size === 0) return;

            const allIds = Array.from(this.selection);
            // The "retryable" hint only exists on currently-visible rows, so
            // cross-page selections send everything and let the server report
            // per-item errors for entries without a stored payload.
            const domBoxes = this.rowBoxes();
            const skippedIds = new Set(
                domBoxes
                    .filter(cb => cb.checked && cb.dataset.retryable === '0')
                    .map(cb => cb.value)
            );
            const ids = allIds.filter(id => ! skippedIds.has(id));

            if (ids.length === 0) {
                alert('None of the selected ' + this.noun + 's can be retried (payload not stored).');
                return;
            }

            const skippedNote = skippedIds.size > 0
                ? ' ' + skippedIds.size + ' selected ' + this.noun + (skippedIds.size === 1 ? ' has' : 's have') + ' no stored payload and will be skipped.'
                : '';

            this.askConfirm({
                title: 'Retry ' + ids.length + ' ' + this.noun + (ids.length === 1 ? '' : 's') + '?',
                body: 'The selected ' + this.noun + 's will be re-dispatched on their original queues.' + skippedNote,
                icon: 'refresh-cw',
                iconColor: 'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20',
                confirmLabel: 'Retry ' + ids.length,
                confirmColor: 'bg-primary text-primary-foreground hover:bg-primary/90',
                onConfirm: () => this.runChunked(this.retryUrl, ids, 'Retrying', 'Retried'),
            });
        }

        confirmDelete() {
            if (! this.deleteUrl || this.selection.size === 0) return;
            const ids = Array.from(this.selection);
            this.askConfirm({
                title: 'Delete ' + ids.length + ' ' + this.noun + (ids.length === 1 ? '' : 's') + '?',
                body: 'Every stored attempt for the selected ' + (ids.length === 1 ? 'UUID' : 'UUIDs') + ' will be permanently removed. This cannot be undone.',
                icon: 'alert-triangle',
                iconColor: 'bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20',
                confirmLabel: 'Delete ' + ids.length,
                confirmColor: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                onConfirm: () => this.runChunked(this.deleteUrl, ids, 'Deleting', 'Deleted'),
            });
        }

        askConfirm({ title, body, icon, iconColor, confirmLabel, confirmColor, onConfirm }) {
            const m = this.modal;
            m.querySelector('[data-jm-bulk-progress-wrap]').classList.add('hidden');
            m.querySelector('[data-jm-bulk-errors-wrap]').classList.add('hidden');
            m.querySelector('[data-jm-bulk-footer]').classList.remove('hidden');
            const cancel = m.querySelector('[data-jm-bulk-cancel]');
            cancel.classList.remove('hidden');
            m.querySelector('[data-jm-bulk-title]').textContent = title;
            m.querySelector('[data-jm-bulk-message]').textContent = body;
            this.setIcon(icon, iconColor);
            const confirm = m.querySelector('[data-jm-bulk-confirm]');
            confirm.textContent = confirmLabel;
            confirm.className = 'inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md ' + confirmColor + ' transition-colors shadow-xs';
            confirm.onclick = () => onConfirm();
            this.openModal();
        }

        setIcon(name, color) {
            const icon = this.modal.querySelector('[data-jm-bulk-icon]');
            const wrap = this.modal.querySelector('[data-jm-bulk-icon-wrap]');
            icon.setAttribute('data-lucide', name);
            wrap.className = 'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full ' + color;
            if (window.__jmRefreshIcons) window.__jmRefreshIcons();
        }

        async runChunked(url, ids, labelVerb, verbPast) {
            const m = this.modal;
            m.querySelector('[data-jm-bulk-title]').textContent = verbPast + ' ' + ids.length + ' ' + this.noun + (ids.length === 1 ? '' : 's');
            m.querySelector('[data-jm-bulk-message]').textContent = 'Processing in chunks of ' + MAX + '. Don\u2019t close this tab.';
            this.setIcon('loader-2', 'bg-primary/10 text-primary');
            m.querySelector('[data-jm-bulk-progress-wrap]').classList.remove('hidden');
            m.querySelector('[data-jm-bulk-progress-bar]').style.width = '0%';
            m.querySelector('[data-jm-bulk-errors-wrap]').classList.add('hidden');
            m.querySelector('[data-jm-bulk-errors]').innerHTML = '';
            m.querySelector('[data-jm-bulk-footer]').classList.add('hidden');
            this.openModal();

            let succeeded = 0, failed = 0;
            const allErrors = {};

            for (let i = 0; i < ids.length; i += MAX) {
                const chunk = ids.slice(i, i + MAX);
                try {
                    const resp = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ ids: chunk }),
                        credentials: 'same-origin',
                    });
                    if (! resp.ok) {
                        failed += chunk.length;
                        chunk.forEach(id => allErrors[id] = 'HTTP ' + resp.status);
                    } else {
                        const data = await resp.json();
                        succeeded += data.succeeded ?? 0;
                        failed += data.failed ?? 0;
                        if (data.errors && typeof data.errors === 'object') Object.assign(allErrors, data.errors);
                    }
                } catch (err) {
                    failed += chunk.length;
                    chunk.forEach(id => allErrors[id] = String(err));
                }

                const done = Math.min(i + MAX, ids.length);
                m.querySelector('[data-jm-bulk-progress-bar]').style.width = ((done / ids.length) * 100).toFixed(1) + '%';
                m.querySelector('[data-jm-bulk-message]').textContent =
                    labelVerb + ' ' + done + ' / ' + ids.length + ' \u00b7 ' + succeeded + ' succeeded, ' + failed + ' failed';
            }

            const allOk = failed === 0;
            this.setIcon(allOk ? 'check-circle-2' : 'alert-triangle',
                allOk ? 'bg-success/10 text-success ring-1 ring-inset ring-success/20'
                      : 'bg-warning/10 text-warning ring-1 ring-inset ring-warning/20');
            m.querySelector('[data-jm-bulk-title]').textContent = verbPast + ' ' + succeeded + ' / ' + ids.length;
            m.querySelector('[data-jm-bulk-message]').textContent = failed > 0
                ? failed + ' ' + (failed === 1 ? 'item' : 'items') + ' could not be ' + verbPast.toLowerCase() + '. See details below.'
                : 'All selected ' + this.noun + 's were ' + verbPast.toLowerCase() + ' successfully.';

            if (Object.keys(allErrors).length > 0) {
                m.querySelector('[data-jm-bulk-errors]').innerHTML = Object.entries(allErrors).map(([id, msg]) =>
                    '<li class="px-3 py-2"><code class="font-mono text-[11px] text-muted-foreground">'
                    + escapeHtml(id) + '</code><div class="text-foreground mt-0.5">'
                    + escapeHtml(msg) + '</div></li>'
                ).join('');
                m.querySelector('[data-jm-bulk-errors-wrap]').classList.remove('hidden');
            }

            m.querySelector('[data-jm-bulk-footer]').classList.remove('hidden');
            const cancel = m.querySelector('[data-jm-bulk-cancel]');
            cancel.classList.add('hidden');
            const confirm = m.querySelector('[data-jm-bulk-confirm]');
            confirm.textContent = 'Reload';
            confirm.onclick = () => window.location.reload();
        }
    }

    window.__JmBulkController = JmBulkController;

    function init() {
        document.querySelectorAll('[data-jm-bulk-scope]').forEach(table => new JmBulkController(table));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
