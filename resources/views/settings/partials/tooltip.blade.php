{{-- CSS-only tooltip. Usage:
       @include('jobs-monitor::settings.partials.tooltip', ['text' => 'Explanation here'])
--}}
<span class="relative inline-flex items-center group">
    <i data-lucide="info" class="text-[14px] text-muted-foreground hover:text-foreground cursor-help transition-colors"></i>
    <span role="tooltip"
          class="pointer-events-none absolute z-30 left-1/2 -translate-x-1/2 bottom-full mb-2 w-64 px-3 py-2 text-xs leading-snug text-popover-foreground bg-popover border border-border rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-opacity">
        {{ $text }}
        <span class="absolute top-full left-1/2 -translate-x-1/2 w-2 h-2 rotate-45 -mt-1 bg-popover border-r border-b border-border"></span>
    </span>
</span>
