@php
    $statusMap = [
        true  => ['label' => 'Enabled',  'cls' => 'bg-success/10 text-success ring-success/25',        'icon' => 'check-circle-2'],
        false => ['label' => 'Disabled', 'cls' => 'bg-muted text-muted-foreground ring-border',         'icon' => 'power-off'],
    ];
    $status = $statusMap[$alerts->enabled];
@endphp

<div class="rounded-xl border border-border bg-card text-card-foreground p-5 shadow-xs">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $alerts->enabled ? 'bg-brand/10 text-brand ring-brand/20' : 'bg-muted text-muted-foreground ring-border' }} ring-1 ring-inset">
                <i data-lucide="toggle-right" class="text-[16px]"></i>
            </span>
            <div>
                <h2 class="text-base font-semibold tracking-tight">Feature toggle</h2>
                <div class="mt-1.5 flex items-center gap-2 text-sm">
                    <span class="text-muted-foreground">Currently:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ring-1 ring-inset {{ $status['cls'] }}">
                        <i data-lucide="{{ $status['icon'] }}" class="text-[12px]"></i>
                        {{ $status['label'] }}
                    </span>
                    @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->enabledSource->value])
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('jobs-monitor.settings.alerts.toggle') }}">
            @csrf
            <input type="hidden" name="enabled" value="{{ $alerts->enabled ? '0' : '1' }}">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md shadow-xs transition-colors
                           {{ $alerts->enabled
                                ? 'bg-secondary text-secondary-foreground hover:bg-accent border border-border'
                                : 'bg-primary text-primary-foreground hover:bg-primary/90' }}">
                <i data-lucide="{{ $alerts->enabled ? 'bell-off' : 'bell-ring' }}" class="text-[14px]"></i>
                {{ $alerts->enabled ? 'Disable' : 'Enable' }} alerts
            </button>
        </form>
    </div>
</div>
