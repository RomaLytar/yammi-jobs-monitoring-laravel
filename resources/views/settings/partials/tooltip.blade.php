{{-- CSS-only tooltip. Usage:
       @include('jobs-monitor::settings.partials.tooltip', ['text' => 'Explanation here'])
--}}
<span class="relative inline-flex items-center group">
    <svg class="w-4 h-4 text-gray-400 hover:text-gray-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4M12 8h.01" />
    </svg>
    <span role="tooltip"
          class="pointer-events-none absolute z-20 left-1/2 -translate-x-1/2 bottom-full mb-2 w-64 px-3 py-2 text-xs leading-snug text-white bg-gray-900 rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-opacity">
        {{ $text }}
        <span class="absolute top-full left-1/2 -translate-x-1/2 -mt-px border-4 border-transparent border-t-gray-900"></span>
    </span>
</span>
