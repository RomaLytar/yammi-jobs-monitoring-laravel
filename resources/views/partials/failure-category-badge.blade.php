@php
    $styles = [
        'transient' => 'bg-yellow-100 text-yellow-800',
        'permanent' => 'bg-red-100 text-red-800',
        'critical' => 'bg-purple-100 text-purple-800',
        'unknown' => 'bg-gray-100 text-gray-600',
    ];
@endphp
@if($value)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $styles[$value] ?? $styles['unknown'] }}">{{ $label }}</span>
@else
    <span class="text-gray-400">—</span>
@endif
