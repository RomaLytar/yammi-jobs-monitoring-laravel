<form method="POST"
      action="{{ route('jobs-monitor.settings.alerts.built-in.update', ['key' => $b->key]) }}"
      class="space-y-4 border border-indigo-200 rounded-md bg-white p-4">
    @csrf

    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Edit {{ $b->key }}</h3>
            <p class="text-xs text-gray-500">
                {{ $b->trigger->label() }}@if($b->triggerValue !== null): <code class="text-xs bg-gray-100 px-1 rounded">{{ $b->triggerValue }}</code>@endif
                · Trigger and identifier are fixed to the shipped built-in.
            </p>
        </div>
        <a href="{{ route('jobs-monitor.settings.alerts') }}" class="text-xs text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    @if($errors->any())
        <div class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-inset ring-red-600/20">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-3 gap-3">
        <div>
            <label for="threshold-{{ $b->key }}" class="block text-xs font-medium text-gray-700 mb-1">Threshold</label>
            <input type="number" id="threshold-{{ $b->key }}" name="threshold" min="1"
                   value="{{ old('threshold', $b->threshold) }}"
                   class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label for="window-{{ $b->key }}" class="block text-xs font-medium text-gray-700 mb-1">Window</label>
            <input type="text" id="window-{{ $b->key }}" name="window" maxlength="16"
                   value="{{ old('window', $b->window) }}"
                   placeholder="5m, 1h, 2d"
                   class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label for="cooldown-{{ $b->key }}" class="block text-xs font-medium text-gray-700 mb-1">Cooldown (min)</label>
            <input type="number" id="cooldown-{{ $b->key }}" name="cooldown_minutes" min="1"
                   value="{{ old('cooldown_minutes', $b->cooldownMinutes) }}"
                   class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="min-attempt-{{ $b->key }}" class="block text-xs font-medium text-gray-700 mb-1">Min attempt</label>
            <input type="number" id="min-attempt-{{ $b->key }}" name="min_attempt" min="1"
                   value="{{ old('min_attempt', $b->minAttempt) }}"
                   placeholder="any"
                   class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <p class="mt-1 text-xs text-gray-500">Silence first-try noise (e.g. 2 = only retries).</p>
        </div>
        <div>
            <span class="block text-xs font-medium text-gray-700 mb-1">Channels</span>
            <div class="flex items-center gap-4 py-2">
                @php $chs = old('channels', $b->channels); @endphp
                @foreach(['slack' => 'Slack', 'mail' => 'Mail'] as $value => $label)
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="channels[]" value="{{ $value }}"
                               {{ in_array($value, (array) $chs, true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1"
                   {{ old('enabled', $b->effectivelyEnabled) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-700">Enabled</span>
        </label>

        <div class="flex items-center gap-2">
            <a href="{{ route('jobs-monitor.settings.alerts') }}"
               class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md bg-white text-gray-700 border border-gray-300 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Save changes
            </button>
        </div>
    </div>
</form>
