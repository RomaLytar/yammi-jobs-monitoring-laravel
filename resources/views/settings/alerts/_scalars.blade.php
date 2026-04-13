<form method="POST" action="{{ route('jobs-monitor.settings.alerts.update') }}"
      class="bg-white rounded-lg border border-gray-200 p-5 space-y-5">
    @csrf

    <div>
        <h2 class="text-lg font-semibold text-gray-900">Identification</h2>
        <p class="mt-1 text-sm text-gray-600">
            Used in alert messages and "Open dashboard" links so a single Slack channel can distinguish environments.
        </p>
    </div>

    @php
        $sourceNameIsAuto = $alerts->sourceNameSource->value === 'auto';
        $monitorUrlIsAuto = $alerts->monitorUrlSource->value === 'auto';
    @endphp

    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <label for="source_name" class="block text-sm font-medium text-gray-700">Source name</label>
            @include('jobs-monitor::settings.partials.tooltip', [
                'text' => 'Short label that identifies this environment in alert messages — e.g. "Production" or "Staging". Shown in the Slack header and email subject so a single channel can distinguish environments. Auto-derived from app.name (+ env) when not set here.',
            ])
            @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->sourceNameSource->value])
        </div>
        <input type="text" name="source_name" id="source_name"
               value="{{ old('source_name', $sourceNameIsAuto ? '' : $alerts->sourceName) }}"
               maxlength="100"
               placeholder="{{ $sourceNameIsAuto ? $alerts->sourceName : 'e.g. Production' }}"
               class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @if($sourceNameIsAuto)
            <p class="mt-1.5 text-xs text-gray-500">
                Auto-derived from <code class="bg-gray-100 px-1 rounded">app.name</code>. Override only if you want a different label.
            </p>
        @endif
        @error('source_name')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <label for="monitor_url" class="block text-sm font-medium text-gray-700">Monitor URL</label>
            @include('jobs-monitor::settings.partials.tooltip', [
                'text' => 'Base URL where this monitor is reachable. Used to render "Open dashboard" / "Open DLQ" deep-link buttons inside alert messages so on-call can jump to the failing job in one click. Auto-derived from app.url + ui.path when not set here.',
            ])
            @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->monitorUrlSource->value])
        </div>
        <input type="url" name="monitor_url" id="monitor_url"
               value="{{ old('monitor_url', $monitorUrlIsAuto ? '' : $alerts->monitorUrl) }}"
               maxlength="500"
               placeholder="{{ $monitorUrlIsAuto ? $alerts->monitorUrl : 'https://monitor.example.com' }}"
               class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @if($monitorUrlIsAuto)
            <p class="mt-1.5 text-xs text-gray-500">
                Auto-derived from <code class="bg-gray-100 px-1 rounded">app.url</code> + <code class="bg-gray-100 px-1 rounded">ui.path</code>. Override only if the monitor lives on a different host.
            </p>
        @endif
        @error('monitor_url')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex justify-end pt-2 border-t border-gray-100">
        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Save settings
        </button>
    </div>
</form>
