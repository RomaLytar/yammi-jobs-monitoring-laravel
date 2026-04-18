@php
    /**
     * Reusable "three-dots" actions menu.
     *
     * Action item shapes:
     *   - ['type' => 'link',    'url' => ..., 'icon' => ..., 'iconColor' => ..., 'label' => ..., 'danger' => bool]
     *   - ['type' => 'form',    'url' => ..., 'method' => 'POST', 'icon' => ..., 'label' => ...]
     *   - ['type' => 'confirm', 'url' => ..., 'icon' => ..., 'label' => ...,
     *      'confirm' => ['title' => ..., 'body' => ..., 'submitLabel' => 'Delete', 'variant' => 'danger']]
     *
     * @var array $actions
     * @var string|null $emptyLabel  placeholder rendered when $actions is empty
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
                        $type = $a['type'] ?? 'link';
                        $rowClass = ($a['danger'] ?? false)
                            ? 'text-destructive hover:bg-destructive/10'
                            : 'hover:bg-accent hover:text-accent-foreground';
                    @endphp
                    @if ($type === 'form')
                        <form method="{{ $a['method'] ?? 'POST' }}" action="{{ $a['url'] }}" class="block">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md {{ $rowClass }}">
                                <i data-lucide="{{ $a['icon'] }}" class="text-[14px] {{ $a['iconColor'] ?? '' }}"></i>
                                {{ $a['label'] }}
                            </button>
                        </form>
                    @elseif ($type === 'confirm')
                        @php
                            $confirm = $a['confirm'] ?? [];
                            $variant = $confirm['variant'] ?? (($a['danger'] ?? false) ? 'danger' : 'primary');
                        @endphp
                        <button type="button"
                                class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md {{ $rowClass }}"
                                data-jm-confirm-trigger
                                data-jm-action="{{ $a['url'] }}"
                                data-jm-method="{{ $a['method'] ?? 'POST' }}"
                                data-jm-title="{{ $confirm['title'] ?? 'Confirm' }}"
                                data-jm-body="{{ $confirm['body'] ?? 'Are you sure?' }}"
                                data-jm-submit="{{ $confirm['submitLabel'] ?? 'Confirm' }}"
                                data-jm-icon="{{ $a['icon'] }}"
                                data-jm-variant="{{ $variant }}">
                            <i data-lucide="{{ $a['icon'] }}" class="text-[14px] {{ $a['iconColor'] ?? '' }}"></i>
                            {{ $a['label'] }}
                        </button>
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
