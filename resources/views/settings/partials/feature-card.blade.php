@php
    $statusStyles = [
        true  => ['label' => 'Enabled',  'classes' => 'bg-green-100 text-green-800'],
        false => ['label' => 'Disabled', 'classes' => 'bg-gray-100 text-gray-700'],
    ];
    $status = $statusStyles[$feature->enabled];
@endphp

<div class="bg-white rounded-lg border border-gray-200 p-5 flex flex-col gap-3">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ $feature->name }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $feature->description }}</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $status['classes'] }}">
            {{ $status['label'] }}
        </span>
    </div>
</div>
