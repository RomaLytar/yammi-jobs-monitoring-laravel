{{-- Source list comes from AlertSettingsData::$channels (single source
     shared with the API response); the partial is layout-only. --}}
<div class="rounded-xl border border-border bg-card text-card-foreground p-5 space-y-4 shadow-xs">
    <div class="flex items-start gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
            <i data-lucide="radio-tower" class="text-[16px]"></i>
        </span>
        <div class="flex-1">
            <h2 class="text-base font-semibold tracking-tight">Notification channels</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Transports that can receive alerts. Secrets are env-only
                (never stored in the database). Set the listed env var,
                restart workers, and the channel lights up.
            </p>
        </div>
    </div>

    <ul class="divide-y divide-border border border-border rounded-lg bg-card overflow-hidden">
        @foreach($alerts->channels as $ch)
            <li class="flex items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-muted text-muted-foreground ring-1 ring-inset ring-border">
                        <i data-lucide="{{ $ch->icon }}" class="text-[14px]"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-medium">{{ $ch->label }}</div>
                        <p class="text-xs text-muted-foreground truncate">{{ $ch->purpose }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <code class="text-[11px] bg-muted px-1.5 py-0.5 rounded font-mono text-muted-foreground">{{ $ch->envVar }}</code>
                    @if($ch->configured)
                        <span class="inline-flex items-center gap-1 rounded-md bg-success/10 text-success ring-1 ring-inset ring-success/25 px-2 py-0.5 text-xs font-medium">
                            <i data-lucide="check-circle-2" class="text-[12px]"></i>
                            configured
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-md bg-muted text-muted-foreground ring-1 ring-inset ring-border px-2 py-0.5 text-xs font-medium">
                            <i data-lucide="circle-dashed" class="text-[12px]"></i>
                            not configured
                        </span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
