@php
    $routeName = $routeName ?? 'jobs-monitor.dashboard';

    $pageBtn = 'inline-flex items-center justify-center h-8 min-w-8 px-2.5 text-xs font-medium rounded-md border border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground transition-colors';
    $pageBtnActive = 'inline-flex items-center justify-center h-8 min-w-8 px-2.5 text-xs font-semibold rounded-md bg-primary text-primary-foreground shadow-xs';
    $pageBtnGhost = 'inline-flex items-center justify-center h-8 min-w-8 px-2.5 text-xs font-medium rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors';
@endphp
<div class="px-5 py-3.5 border-t border-border flex flex-wrap items-center justify-between gap-3">
    <div class="text-xs text-muted-foreground">
        Page <span class="font-medium text-foreground tabular-nums">{{ $currentPage }}</span>
        of <span class="font-medium text-foreground tabular-nums">{{ $lastPage }}</span>
    </div>
    <div class="flex items-center gap-1">
        @if($currentPage > 1)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $currentPage - 1])) }}"
               class="{{ $pageBtn }} gap-1">
                <i data-lucide="chevron-left" class="text-[13px]"></i>
                Prev
            </a>
        @endif

        @php
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp

        @if($start > 1)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => 1])) }}" class="{{ $pageBtn }}">1</a>
            @if($start > 2)<span class="{{ $pageBtnGhost }} pointer-events-none">…</span>@endif
        @endif

        @for($p = $start; $p <= $end; $p++)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $p])) }}"
               class="{{ $p === $currentPage ? $pageBtnActive : $pageBtn }} tabular-nums">{{ $p }}</a>
        @endfor

        @if($end < $lastPage)
            @if($end < $lastPage - 1)<span class="{{ $pageBtnGhost }} pointer-events-none">…</span>@endif
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $lastPage])) }}" class="{{ $pageBtn }} tabular-nums">{{ $lastPage }}</a>
        @endif

        @if($currentPage < $lastPage)
            <a href="{{ route($routeName, array_merge($extraParams, [$pageParam => $currentPage + 1])) }}"
               class="{{ $pageBtn }} gap-1">
                Next
                <i data-lucide="chevron-right" class="text-[13px]"></i>
            </a>
        @endif

        <form method="GET" action="{{ route($routeName) }}" class="flex items-center gap-1 ml-2">
            @foreach($extraParams as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <input type="number" name="{{ $pageParam }}" min="1" max="{{ $lastPage }}" placeholder="#"
                   class="h-8 w-14 rounded-md border border-input bg-card text-xs text-foreground px-2 tabular-nums focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring">
            <button type="submit" class="{{ $pageBtn }}">Go</button>
        </form>
    </div>
</div>
