@php
    $badgeStyles = [
        'db'     => ['label' => 'from DB',     'classes' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'],
        'config' => ['label' => 'from config', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-600/20'],
        'auto'   => ['label' => 'auto',        'classes' => 'bg-sky-50 text-sky-700 ring-sky-600/20'],
    ];
    $b = $badgeStyles[$source] ?? null;
@endphp

@if($b !== null)
    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $b['classes'] }}">
        {{ $b['label'] }}
    </span>
@endif
