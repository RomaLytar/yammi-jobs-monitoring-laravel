@php
    /**
     * Sticky action bar + progress/result modal for a bulk selection.
     *
     * Props:
     *   - $scope          (string) unique id, e.g. 'dlq' or 'failures'
     *   - $retryEnabled   (bool)   show Retry button
     *   - $showDelete     (bool)   show Delete button (DLQ only)
     *   - $noun           (string) 'job' / 'entry' — used in confirm copy
     */
    $scope = $scope ?? 'default';
    $retryEnabled = $retryEnabled ?? true;
    $showDelete = $showDelete ?? false;
    $noun = $noun ?? 'job';
@endphp

<div id="jm-bulk-bar-{{ $scope }}"
     class="hidden fixed inset-x-0 bottom-4 z-40 pointer-events-none px-4">
    <div class="pointer-events-auto mx-auto max-w-3xl flex items-center justify-between gap-3 rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border px-4 py-3">
        <div class="flex items-center gap-2 text-sm">
            <i data-lucide="check-square" class="text-[16px] text-primary"></i>
            <span><span data-jm-bulk-count>0</span> selected</span>
            <span data-jm-bulk-truncated class="hidden text-[11px] text-muted-foreground ml-2"></span>
        </div>
        <div class="flex items-center gap-2">
            @if($retryEnabled)
                <button type="button"
                        class="inline-flex items-center gap-1.5 h-9 px-3 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs"
                        data-jm-bulk-retry>
                    <i data-lucide="refresh-cw" class="text-[14px]"></i>
                    Retry selected
                </button>
            @endif
            @if($showDelete)
                <button type="button"
                        class="inline-flex items-center gap-1.5 h-9 px-3 text-sm font-semibold rounded-md bg-destructive text-destructive-foreground hover:bg-destructive/90 transition-colors shadow-xs"
                        data-jm-bulk-delete>
                    <i data-lucide="trash-2" class="text-[14px]"></i>
                    Delete selected
                </button>
            @endif
            <button type="button"
                    class="inline-flex items-center h-9 px-3 text-sm font-medium rounded-md border border-border bg-card hover:bg-accent transition-colors"
                    data-jm-bulk-clear>Clear</button>
        </div>
    </div>
</div>

<div id="jm-bulk-modal-{{ $scope }}"
     class="hidden fixed inset-0 z-50 overflow-y-auto"
     role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity" data-jm-bulk-backdrop></div>
        <div class="relative w-full max-w-lg transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border animate-slide-down">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full" data-jm-bulk-icon-wrap>
                        <i data-lucide="refresh-cw" class="text-[18px]" data-jm-bulk-icon></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-semibold" data-jm-bulk-title>Confirm bulk action</h3>
                        <p class="mt-2 text-sm text-muted-foreground" data-jm-bulk-message></p>
                        <div class="hidden mt-3 w-full h-2 rounded-full bg-muted overflow-hidden" data-jm-bulk-progress-wrap>
                            <div class="h-2 bg-primary transition-all" style="width: 0" data-jm-bulk-progress-bar></div>
                        </div>
                        <div class="hidden mt-3 max-h-48 overflow-y-auto rounded-lg border border-border bg-card text-xs" data-jm-bulk-errors-wrap>
                            <div class="px-3 py-2 border-b border-border font-medium text-destructive">Errors</div>
                            <ul class="divide-y divide-border" data-jm-bulk-errors></ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-muted/40 px-6 py-3 flex justify-end gap-2 border-t border-border" data-jm-bulk-footer>
                <button type="button"
                        class="inline-flex items-center h-9 px-4 text-sm font-medium rounded-md border border-border bg-card hover:bg-accent transition-colors"
                        data-jm-bulk-cancel>Cancel</button>
                <button type="button"
                        class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs"
                        data-jm-bulk-confirm>Confirm</button>
            </div>
        </div>
    </div>
</div>
