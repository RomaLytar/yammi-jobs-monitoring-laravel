@php
    $isEditing = $editing === $b->key;
@endphp

<tr class="{{ $isEditing ? 'bg-indigo-50/30' : '' }}">
    <td class="px-4 py-3 align-top">
        <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $b->key }}</code>
        @if($b->hasOverride)
            <div class="mt-1 text-xs text-indigo-700">(customized)</div>
        @endif
    </td>
    <td class="px-4 py-3 align-top">
        {{ $b->trigger->label() }}
        @if($b->triggerValue !== null)
            <span class="text-gray-500">: {{ $b->triggerValue }}</span>
        @endif
    </td>
    <td class="px-4 py-3 align-top">{{ $b->threshold }}</td>
    <td class="px-4 py-3 align-top">{{ $b->window ?? '—' }}</td>
    <td class="px-4 py-3 align-top">
        @foreach($b->channels as $ch)
            <span class="inline-flex items-center px-1.5 py-0.5 text-xs rounded bg-gray-100 text-gray-700">{{ $ch }}</span>
        @endforeach
    </td>
    <td class="px-4 py-3 align-top">
        @if($b->effectivelyEnabled)
            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Enabled</span>
        @else
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">Disabled</span>
        @endif
    </td>
    <td class="px-4 py-3 align-top text-right">
        <details class="relative inline-block text-left">
            <summary class="list-none cursor-pointer inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100 text-gray-500 hover:text-gray-800" aria-haspopup="menu">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <circle cx="8" cy="3" r="1.5" />
                    <circle cx="8" cy="8" r="1.5" />
                    <circle cx="8" cy="13" r="1.5" />
                </svg>
                <span class="sr-only">Actions for {{ $b->key }}</span>
            </summary>
            <div class="absolute right-0 top-full mt-1 z-10 w-48 rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none" role="menu">
                <div class="py-1">
                    <form method="POST" action="{{ route('jobs-monitor.settings.alerts.built-in.toggle', ['key' => $b->key]) }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $b->effectivelyEnabled ? '0' : '1' }}">
                        <button type="submit" class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            {{ $b->effectivelyEnabled ? 'Disable' : 'Enable' }}
                        </button>
                    </form>
                    <a href="{{ route('jobs-monitor.settings.alerts', ['editing' => $b->key]) }}#rule-{{ $b->key }}"
                       class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
                        Edit
                    </a>
                    @if($b->hasOverride)
                        <form method="POST" action="{{ route('jobs-monitor.settings.alerts.built-in.reset', ['key' => $b->key]) }}"
                              onsubmit="return confirm('Reset {{ $b->key }} to shipped defaults? This discards your edits.')">
                            @csrf
                            <button type="submit" class="block w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50">
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
<tr class="bg-indigo-50/30" id="rule-{{ $b->key }}">
    <td colspan="7" class="px-4 py-4">
        @include('jobs-monitor::settings.alerts._built-in-edit-form', ['b' => $b])
    </td>
</tr>
@endif
