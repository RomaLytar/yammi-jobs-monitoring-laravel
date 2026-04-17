@php
    $driverIcons = ['mysql' => 'database', 'pgsql' => 'database', 'sqlite' => 'hard-drive', 'unknown' => 'server'];
    $icon        = $driverIcons[$status->driver] ?? 'server';
    $isActive    = $active ?? false;
@endphp

<div class="rounded-xl border p-5 flex flex-col gap-4 shadow-xs transition-colors
            {{ $isActive
                ? 'border-brand bg-brand/5 ring-1 ring-inset ring-brand/20'
                : 'border-border bg-card text-card-foreground' }}">

    {{-- Header row --}}
    <div class="flex items-start gap-3">
        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg
                     {{ $isActive ? 'bg-brand/15 text-brand ring-1 ring-inset ring-brand/25' : 'bg-brand/10 text-brand ring-1 ring-inset ring-brand/20' }}">
            <i data-lucide="{{ $icon }}" class="text-[16px]"></i>
        </span>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold tracking-tight">{{ $label }}</h2>
                @if($isActive)
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-brand/15 text-brand ring-1 ring-inset ring-brand/20">
                        <i data-lucide="zap" class="text-[10px]"></i>
                        Active
                    </span>
                @endif
            </div>
            <p class="mt-0.5 text-sm font-mono text-muted-foreground truncate">{{ $status->name }}</p>
        </div>
        @if($status->reachable)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ring-1 ring-inset bg-success/10 text-success ring-success/25">
                <i data-lucide="wifi" class="text-[12px]"></i>
                Reachable
            </span>
        @else
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ring-1 ring-inset bg-destructive/10 text-destructive ring-destructive/25">
                <i data-lucide="wifi-off" class="text-[12px]"></i>
                Unreachable
            </span>
        @endif
    </div>

    {{-- Details grid --}}
    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm border border-border rounded-lg px-4 py-3 bg-muted/30">

        <dt class="text-muted-foreground">Driver</dt>
        <dd class="font-mono font-medium">
            <span class="inline-flex items-center gap-1">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-muted text-muted-foreground text-[10px] font-bold ring-1 ring-border">
                    {{ strtoupper(substr($status->driver, 0, 2)) }}
                </span>
                {{ $status->driver }}
            </span>
        </dd>

        <dt class="text-muted-foreground">Database</dt>
        <dd class="font-mono font-medium truncate">{{ $status->database }}</dd>

        <dt class="text-muted-foreground">Migrations</dt>
        <dd>
            @if(!$status->reachable)
                <span class="text-muted-foreground">—</span>
            @elseif($status->migrated)
                <span class="inline-flex items-center gap-1 text-success">
                    <i data-lucide="check-circle-2" class="text-[13px]"></i>
                    Applied
                </span>
            @else
                <span class="inline-flex items-center gap-1 text-warning">
                    <i data-lucide="alert-triangle" class="text-[13px]"></i>
                    Not migrated
                </span>
            @endif
        </dd>

        <dt class="text-muted-foreground">Rows</dt>
        <dd class="font-medium tabular-nums">
            @if($status->reachable && $status->migrated)
                {{ number_format($status->rowCount) }}
            @else
                <span class="text-muted-foreground">—</span>
            @endif
        </dd>

    </dl>

</div>
