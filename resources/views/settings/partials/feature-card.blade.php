@php
    $statusMap = [
        true  => ['label' => 'Enabled',  'cls' => 'bg-success/10 text-success ring-success/25',       'icon' => 'check-circle-2'],
        false => ['label' => 'Disabled', 'cls' => 'bg-muted text-muted-foreground ring-border',        'icon' => 'power-off'],
    ];
    $status = $statusMap[$feature->enabled];
@endphp

<div class="group relative overflow-hidden rounded-xl border border-border bg-card text-card-foreground p-5 flex flex-col gap-3 shadow-xs hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
            <h2 class="text-base font-semibold tracking-tight">{{ $feature->name }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">{{ $feature->description }}</p>
        </div>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ring-1 ring-inset {{ $status['cls'] }}">
            <i data-lucide="{{ $status['icon'] }}" class="text-[12px]"></i>
            {{ $status['label'] }}
        </span>
    </div>
    @if($feature->manageRouteName !== null && \Illuminate\Support\Facades\Route::has($feature->manageRouteName))
        <div class="pt-3 border-t border-border">
            <a href="{{ route($feature->manageRouteName) }}"
               class="inline-flex items-center gap-1 text-sm font-medium text-brand hover:text-brand/80 transition-colors">
                Configure
                <i data-lucide="arrow-right" class="text-[13px]"></i>
            </a>
        </div>
    @endif
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-12 -right-12 h-32 w-32 rounded-full bg-gradient-to-tr from-transparent to-brand/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
</div>
