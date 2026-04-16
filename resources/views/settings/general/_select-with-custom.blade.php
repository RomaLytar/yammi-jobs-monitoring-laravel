{{-- Select dropdown using the package's custom popover style, with a "Custom value" option.
     @param string $name    — form field name
     @param string $value   — current value
     @param string $fieldId — HTML id
     @param array  $options — value => label map
     @param string $description — hint text for custom input
--}}
@php
    $id = 'jm-sel-' . bin2hex(random_bytes(4));
    $isPreset = array_key_exists((string) $value, $options);
    $isCustom = !$isPreset && $value !== '' && $value !== null;
    $triggerLabel = $isPreset
        ? $options[(string) $value]
        : ($isCustom ? "Custom: {$value}" : ($options[array_key_first($options)] ?? 'Select…'));
    $isActive = true;
@endphp

<div class="relative" data-jm-select data-jm-select-id="{{ $id }}" data-jm-select-custom>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-jm-select-input id="{{ $fieldId }}">

    <button type="button"
            class="inline-flex items-center justify-between gap-2 h-9 w-full rounded-md border bg-card text-sm px-3 transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring
                   {{ $isActive
                        ? 'border-brand/40 ring-2 ring-brand/15 bg-brand/5 text-foreground'
                        : 'border-input text-foreground hover:bg-accent/40 hover:border-ring/40' }}"
            data-jm-select-trigger
            aria-haspopup="listbox"
            aria-expanded="false">
        <span class="truncate font-medium" data-jm-select-label>{{ $triggerLabel }}</span>
        <i data-lucide="chevron-down" class="text-[14px] text-muted-foreground shrink-0 transition-transform" data-jm-select-caret></i>
    </button>

    <div class="hidden absolute z-30 mt-1 left-0 min-w-full w-max max-w-[min(20rem,90vw)] rounded-md border border-border bg-popover text-popover-foreground shadow-lg ring-1 ring-black/5 dark:ring-white/5 animate-slide-down overflow-hidden"
         data-jm-select-dropdown
         role="listbox">
        <ul class="p-1 max-h-60 overflow-y-auto overscroll-contain" data-jm-select-list>
            @foreach($options as $optValue => $optLabel)
                @php $selected = !$isCustom && (string) $value === (string) $optValue; @endphp
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

            {{-- Custom value divider + input --}}
            <li class="border-t border-border mt-1 pt-1">
                <div class="px-2 py-1.5 text-xs text-muted-foreground font-medium">Custom value</div>
            </li>
            <li class="px-2 pb-1.5" data-jm-custom-row>
                <div class="flex items-center gap-1.5">
                    <input type="text"
                           data-jm-custom-input
                           value="{{ $isCustom ? $value : '' }}"
                           placeholder="e.g. */3 * * * *"
                           class="flex-1 h-8 rounded-md border border-input bg-card px-2.5 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring transition-[box-shadow,border-color]"
                           onclick="event.stopPropagation()">
                    <button type="button"
                            data-jm-custom-apply
                            class="inline-flex items-center h-8 px-2.5 text-xs font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs transition-colors">
                        Apply
                    </button>
                </div>
                <p class="mt-1 text-[11px] text-muted-foreground leading-snug">{{ $description }}</p>
            </li>
        </ul>
    </div>
</div>
