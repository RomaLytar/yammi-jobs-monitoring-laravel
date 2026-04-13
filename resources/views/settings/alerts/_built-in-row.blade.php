@php
    $isEditing = $editing === $b->key;
    $channelIcon = ['slack' => 'slack', 'mail' => 'mail'];
@endphp

<tr class="{{ $isEditing ? 'bg-brand/5' : 'hover:bg-muted/40 transition-colors' }}">
    <td class="px-4 py-3 align-top">
        <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">{{ $b->key }}</code>
        @if($b->hasOverride)
            <div class="mt-1 inline-flex items-center gap-1 text-[11px] text-brand">
                <i data-lucide="pencil" class="text-[10px]"></i>
                customized
            </div>
        @endif
    </td>
    <td class="px-4 py-3 align-top">
        <span class="font-medium">{{ $b->trigger->label() }}</span>
        @if($b->triggerValue !== null)
            <span class="text-muted-foreground">: {{ $b->triggerValue }}</span>
        @endif
    </td>
    <td class="px-4 py-3 align-top tabular-nums">{{ $b->threshold }}</td>
    <td class="px-4 py-3 align-top tabular-nums text-muted-foreground">{{ $b->window ?? '—' }}</td>
    <td class="px-4 py-3 align-top">
        <div class="flex flex-wrap gap-1">
            @foreach($b->channels as $ch)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[11px] rounded-md bg-muted text-muted-foreground ring-1 ring-inset ring-border">
                    <i data-lucide="{{ $channelIcon[$ch] ?? 'radio' }}" class="text-[11px]"></i>
                    {{ $ch }}
                </span>
            @endforeach
        </div>
    </td>
    <td class="px-4 py-3 align-top">
        @if($b->effectivelyEnabled)
            <span class="inline-flex items-center gap-1 rounded-md bg-success/10 text-success ring-1 ring-inset ring-success/25 px-2 py-0.5 text-xs font-medium">
                <i data-lucide="check-circle-2" class="text-[12px]"></i>
                Enabled
            </span>
        @else
            <span class="inline-flex items-center gap-1 rounded-md bg-muted text-muted-foreground ring-1 ring-inset ring-border px-2 py-0.5 text-xs font-medium">
                <i data-lucide="power-off" class="text-[12px]"></i>
                Disabled
            </span>
        @endif
    </td>
    <td class="px-4 py-3 align-top text-right">
        <details class="relative inline-block text-left">
            <summary class="list-none cursor-pointer inline-flex items-center justify-center h-8 w-8 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors" aria-haspopup="menu">
                <i data-lucide="more-horizontal" class="text-[16px]"></i>
                <span class="sr-only">Actions for {{ $b->key }}</span>
            </summary>
            <div class="absolute right-0 top-full mt-1 z-10 w-52 rounded-lg bg-popover text-popover-foreground shadow-lg ring-1 ring-border focus:outline-none animate-slide-down" role="menu">
                <div class="p-1">
                    <form method="POST" action="{{ route('jobs-monitor.settings.alerts.built-in.toggle', ['key' => $b->key]) }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $b->effectivelyEnabled ? '0' : '1' }}">
                        <button type="submit" class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground">
                            <i data-lucide="{{ $b->effectivelyEnabled ? 'bell-off' : 'bell-ring' }}" class="text-[14px] text-brand"></i>
                            {{ $b->effectivelyEnabled ? 'Disable' : 'Enable' }}
                        </button>
                    </form>
                    <a href="{{ route('jobs-monitor.settings.alerts', ['editing' => $b->key]) }}#rule-{{ $b->key }}"
                       class="flex items-center gap-2 px-2.5 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground" role="menuitem">
                        <i data-lucide="pencil" class="text-[14px] text-brand"></i>
                        Edit
                    </a>
                    @if($b->hasOverride)
                        <div class="h-px bg-border my-1"></div>
                        <form method="POST" action="{{ route('jobs-monitor.settings.alerts.built-in.reset', ['key' => $b->key]) }}"
                              onsubmit="return confirm('Reset {{ $b->key }} to shipped defaults? This discards your edits.')">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm text-destructive rounded-md hover:bg-destructive/10">
                                <i data-lucide="rotate-ccw" class="text-[14px]"></i>
                                Reset to default
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </details>
    </td>
</tr>

@if($isEditing)
<tr class="bg-brand/5" id="rule-{{ $b->key }}">
    <td colspan="7" class="px-4 py-4">
        @include('jobs-monitor::settings.alerts._built-in-edit-form', ['b' => $b])
    </td>
</tr>
@endif
