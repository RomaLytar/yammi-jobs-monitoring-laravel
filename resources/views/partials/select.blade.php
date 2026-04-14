@php
    /** @var string $name */
    /** @var array<string|int, string> $options map of value => label */
    /** @var string|null $value */
    /** @var string|null $placeholder */
    $placeholder = $placeholder ?? 'Select…';
    $value = $value ?? '';
    $triggerLabel = $options[$value] ?? ($value === '' ? ($options[''] ?? $placeholder) : $value);
    $isActive = $value !== '' && $value !== null;
    $id = 'jm-sel-' . bin2hex(random_bytes(4));
@endphp

<div class="relative" data-jm-select data-jm-select-id="{{ $id }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-jm-select-input>

    <button type="button"
            class="inline-flex items-center justify-between gap-2 h-9 min-w-[9rem] rounded-md border bg-card text-sm px-3 transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring
                   {{ $isActive
                        ? 'border-brand/40 ring-2 ring-brand/15 bg-brand/5 text-foreground'
                        : 'border-input text-foreground hover:bg-accent/40 hover:border-ring/40' }}"
            data-jm-select-trigger
            aria-haspopup="listbox"
            aria-expanded="false">
        <span class="truncate {{ $isActive ? 'font-medium' : 'text-muted-foreground/90' }}" data-jm-select-label>{{ $triggerLabel }}</span>
        <i data-lucide="chevron-down" class="text-[14px] text-muted-foreground shrink-0 transition-transform" data-jm-select-caret></i>
    </button>

    <div class="hidden absolute z-30 mt-1 left-0 min-w-full w-max max-w-[min(20rem,90vw)] rounded-md border border-border bg-popover text-popover-foreground shadow-lg ring-1 ring-black/5 dark:ring-white/5 animate-slide-down overflow-hidden"
         data-jm-select-dropdown
         role="listbox">
        <ul class="p-1 max-h-60 overflow-y-auto overscroll-contain" data-jm-select-list>
            @foreach($options as $optValue => $optLabel)
                @php $selected = (string) $value === (string) $optValue; @endphp
                <li role="option"
                    tabindex="-1"
                    aria-selected="{{ $selected ? 'true' : 'false' }}"
                    data-jm-select-option
                    data-value="{{ $optValue }}"
                    data-label="{{ $optLabel }}"
                    class="flex items-center gap-2 px-2 py-1.5 text-sm rounded-sm cursor-pointer
                           {{ $selected ? 'bg-brand/10 text-foreground font-medium' : 'text-foreground hover:bg-accent hover:text-accent-foreground' }}">
                    <span class="w-4 inline-flex justify-center">
                        @if($selected)
                            <i data-lucide="check" class="text-[14px] text-brand"></i>
                        @endif
                    </span>
                    <span class="truncate">{{ $optLabel }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
