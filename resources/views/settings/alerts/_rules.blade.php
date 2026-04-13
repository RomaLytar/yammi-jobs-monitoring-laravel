<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="p-5 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-900">Alert rules</h2>
            @include('jobs-monitor::settings.partials.tooltip', [
                'text' => 'Shipped rules decide when an alert fires. Toggle off to silence a rule without deleting it; click Edit to tune threshold, window, cooldown or channels. Reset returns the row to the shipped default.',
            ])
        </div>
        <p class="mt-1 text-sm text-gray-600">
            Built-in rules that ship with the package. Disable silences the rule on the next scheduler tick (~1 minute). Reset discards overrides and returns to the shipped default.
        </p>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-2 text-left">Rule</th>
                <th class="px-4 py-2 text-left">Trigger</th>
                <th class="px-4 py-2 text-left">Threshold</th>
                <th class="px-4 py-2 text-left">Window</th>
                <th class="px-4 py-2 text-left">Channels</th>
                <th class="px-4 py-2 text-left">State</th>
                <th class="px-4 py-2 text-right w-12">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($rulesOverview->builtInRules as $b)
                @include('jobs-monitor::settings.alerts._built-in-row', ['b' => $b, 'editing' => $editing])
            @endforeach
        </tbody>
    </table>
</div>
