<div class="bg-white rounded-lg border border-gray-200 p-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Feature toggle</h2>
            <div class="mt-1 flex items-center gap-2 text-sm">
                <span class="text-gray-600">Currently:</span>
                @if($alerts->enabled)
                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                        Enabled
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                        Disabled
                    </span>
                @endif
                @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->enabledSource->value])
            </div>
        </div>

        <form method="POST" action="{{ route('jobs-monitor.settings.alerts.toggle') }}">
            @csrf
            <input type="hidden" name="enabled" value="{{ $alerts->enabled ? '0' : '1' }}">
            <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md text-white {{ $alerts->enabled ? 'bg-gray-700 hover:bg-gray-800' : 'bg-indigo-600 hover:bg-indigo-700' }}">
                {{ $alerts->enabled ? 'Disable' : 'Enable' }} alerts
            </button>
        </form>
    </div>
</div>
