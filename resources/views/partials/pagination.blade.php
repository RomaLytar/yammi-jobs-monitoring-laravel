@php
    $routeName = $routeName ?? 'jobs-monitor.dashboard';
@endphp
<div class="px-5 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3">
    <div class="text-sm text-gray-500">Page {{ $currentPage }} of {{ $lastPage }}</div>
    <div class="flex items-center gap-2">
        @if($currentPage > 1)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $currentPage - 1])) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200">Prev</a>
        @endif

        @php
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp

        @if($start > 1)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => 1])) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200">1</a>
            @if($start > 2)<span class="text-gray-400 text-xs">...</span>@endif
        @endif

        @for($p = $start; $p <= $end; $p++)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $p])) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md {{ $p === $currentPage ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $p }}</a>
        @endfor

        @if($end < $lastPage)
            @if($end < $lastPage - 1)<span class="text-gray-400 text-xs">...</span>@endif
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $lastPage])) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200">{{ $lastPage }}</a>
        @endif

        @if($currentPage < $lastPage)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $currentPage + 1])) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200">Next</a>
        @endif

        <form method="GET" action="{{ route($routeName) }}" class="flex items-center gap-1 ml-2">
            @foreach($extraParams as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <input type="number" name="{{ $pageParam }}" min="1" max="{{ $lastPage }}" placeholder="#"
                   class="border border-gray-300 rounded-md px-2 py-1.5 text-xs w-16 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="bg-gray-100 text-gray-600 hover:bg-gray-200 px-2 py-1.5 text-xs font-medium rounded-md">Go</button>
        </form>
    </div>
</div>
