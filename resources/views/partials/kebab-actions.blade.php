@php
    /**
     * Reusable "three-dots" actions menu. Pass an array of actions; on
     * click the kebab opens a popover listing them. Empty arrays render
     * an inline placeholder text.
     *
     * @var array<int, array{type: string, url: string, method?: string, icon: string, iconColor?: string, label: string, danger?: bool}> $actions
     * @var string|null $emptyLabel  Shown when actions array is empty (e.g. "no payload", "pruned").
     */
    $actions = $actions ?? [];
    $emptyLabel = $emptyLabel ?? null;
@endphp
@if (count($actions) === 0)
    @if ($emptyLabel)
        <span class="text-[11px] text-muted-foreground italic">{{ $emptyLabel }}</span>
    @else
        <span class="text-xs text-muted-foreground">—</span>
    @endif
@else
    <div class="relative inline-block text-left" data-jm-kebab>
        <button type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-ring"
                title="Actions"
                onclick="__jmToggleKebab(this)">
            <i data-lucide="more-horizontal" class="text-[16px]"></i>
        </button>
        <div class="hidden absolute right-0 z-20 mt-1 w-52 origin-top-right rounded-lg bg-popover text-popover-foreground shadow-lg ring-1 ring-border focus:outline-none animate-slide-down"
             data-jm-kebab-dropdown>
            <div class="p-1">
                @foreach ($actions as $a)
                    @php
                        $rowClass = ($a['danger'] ?? false)
                            ? 'text-destructive hover:bg-destructive/10'
                            : 'hover:bg-accent hover:text-accent-foreground';
                    @endphp
                    @if (($a['type'] ?? 'link') === 'form')
                        <form method="{{ $a['method'] ?? 'POST' }}" action="{{ $a['url'] }}" class="block">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md {{ $rowClass }}">
                                <i data-lucide="{{ $a['icon'] }}" class="text-[14px] {{ $a['iconColor'] ?? '' }}"></i>
                                {{ $a['label'] }}
                            </button>
                        </form>
                    @else
                        <a href="{{ $a['url'] }}" class="flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md {{ $rowClass }}">
                            <i data-lucide="{{ $a['icon'] }}" class="text-[14px] {{ $a['iconColor'] ?? '' }}"></i>
                            {{ $a['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif
