@php
    $styles = [
        'processing' => 'bg-yellow-100 text-yellow-800',
        'processed' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
    ];
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $styles[$value] ?? 'bg-gray-100 text-gray-600' }}">{{ $value }}</span>
