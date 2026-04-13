<div class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
    <div>
        <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-900">Mail recipients</h2>
            @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->recipientsSource->value])
        </div>
        <p class="mt-1 text-sm text-gray-600">
            Addresses that receive email when an alert rule routed to <code class="text-xs bg-gray-100 px-1 rounded">mail</code> fires.
        </p>
    </div>

    @if(empty($alerts->recipients))
        <div class="text-sm text-gray-500 italic">No recipients yet. Add one below.</div>
    @else
        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-md">
            @foreach($alerts->recipients as $email)
                <li class="flex items-center justify-between px-3 py-2">
                    <span class="text-sm text-gray-800">{{ $email }}</span>
                    @if($alerts->recipientsSource->value === 'db')
                        <form method="POST"
                              action="{{ route('jobs-monitor.settings.alerts.recipients.delete', ['email' => $email]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">Remove</button>
                        </form>
                    @else
                        <span class="text-xs text-gray-400">read-only</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('jobs-monitor.settings.alerts.recipients.add') }}"
          class="space-y-2 pt-3 border-t border-gray-100">
        @csrf
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <label for="email" class="block text-sm font-medium text-gray-700">Add recipients</label>
                @include('jobs-monitor::settings.partials.tooltip', [
                    'text' => 'One email per recipient. Add multiple at once by separating with comma, semicolon, space or newline. Each is validated and de-duplicated against the existing list before saving.',
                ])
            </div>
            <textarea name="email" id="email"
                      rows="2"
                      placeholder="ops@example.com, sre@example.com"
                      class="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      required>{{ old('email') }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Multiple addresses allowed — separate with comma, semicolon, space or newline.</p>
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('emails')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('emails.*')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Add recipients
            </button>
        </div>
    </form>
</div>
