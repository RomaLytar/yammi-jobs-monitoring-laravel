@php
    $map = [
        'processing' => ['tone' => 'warning',     'icon' => 'loader',       'label' => 'Processing'],
        'processed'  => ['tone' => 'success',     'icon' => 'check',        'label' => 'Processed'],
        'failed'     => ['tone' => 'destructive', 'icon' => 'alert-circle', 'label' => 'Failed'],
    ];
    $cfg = $map[$value] ?? ['tone' => 'muted', 'icon' => 'circle-dashed', 'label' => $value];

    $tones = [
        'success'     => 'bg-success/10 text-success ring-success/25',
        'warning'     => 'bg-warning/10 text-warning ring-warning/25',
        'destructive' => 'bg-destructive/10 text-destructive ring-destructive/25',
        'muted'       => 'bg-muted text-muted-foreground ring-border',
    ];
@endphp
<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ring-1 ring-inset {{ $tones[$cfg['tone']] }}">
    <i data-lucide="{{ $cfg['icon'] }}" class="text-[12px] {{ $value === 'processing' ? 'animate-spin' : '' }}"></i>
    {{ $cfg['label'] }}
</span>
