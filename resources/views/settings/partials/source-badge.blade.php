@php
    $map = [
        'db'     => ['label' => 'from DB',     'cls' => 'bg-brand/10 text-brand ring-brand/25',         'icon' => 'database'],
        'config' => ['label' => 'from config', 'cls' => 'bg-warning/10 text-warning ring-warning/25',   'icon' => 'file-cog'],
        'auto'   => ['label' => 'auto',        'cls' => 'bg-info/10 text-info ring-info/25',            'icon' => 'wand-2'],
    ];
    $b = $map[$source] ?? null;
@endphp

@if($b !== null)
    <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $b['cls'] }}">
        <i data-lucide="{{ $b['icon'] }}" class="text-[11px]"></i>
        {{ $b['label'] }}
    </span>
@endif
