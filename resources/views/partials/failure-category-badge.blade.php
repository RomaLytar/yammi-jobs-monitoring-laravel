@php
    $map = [
        'transient' => ['cls' => 'bg-warning/10 text-warning ring-warning/25',        'icon' => 'refresh-cw'],
        'permanent' => ['cls' => 'bg-destructive/10 text-destructive ring-destructive/25', 'icon' => 'x-octagon'],
        'critical'  => ['cls' => 'bg-fuchsia-500/10 text-fuchsia-500 ring-fuchsia-500/25', 'icon' => 'zap'],
        'unknown'   => ['cls' => 'bg-muted text-muted-foreground ring-border',        'icon' => 'help-circle'],
    ];
    $cfg = $map[$value] ?? $map['unknown'];
@endphp
@if($value)
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ring-1 ring-inset {{ $cfg['cls'] }}">
        <i data-lucide="{{ $cfg['icon'] }}" class="text-[12px]"></i>
        {{ $label }}
    </span>
@else
    <span class="text-muted-foreground text-sm">—</span>
@endif
