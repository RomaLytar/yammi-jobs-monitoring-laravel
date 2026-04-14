<div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
    <div class="px-5 py-4 border-b border-border flex items-start gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-warning/10 text-warning ring-1 ring-inset ring-warning/20">
            <i data-lucide="siren" class="text-[16px]"></i>
        </span>
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold tracking-tight">Alert rules</h2>
                @include('jobs-monitor::settings.partials.tooltip', [
                    'text' => 'Shipped rules decide when an alert fires. Toggle off to silence a rule without deleting it; click Edit to tune threshold, window, cooldown or channels. Reset returns the row to the shipped default.',
                ])
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Built-in rules that ship with the package. Disable silences the rule on the next scheduler tick (~1 minute). Reset discards overrides and returns to the shipped default.
            </p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                    <th class="text-left font-medium px-4 py-2.5">Rule</th>
                    <th class="text-left font-medium px-4 py-2.5">Trigger</th>
                    <th class="text-left font-medium px-4 py-2.5">Threshold</th>
                    <th class="text-left font-medium px-4 py-2.5">Window</th>
                    <th class="text-left font-medium px-4 py-2.5">Channels</th>
                    <th class="text-left font-medium px-4 py-2.5">State</th>
                    <th class="text-right font-medium px-4 py-2.5 w-12">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($rulesOverview->builtInRules as $b)
                    @include('jobs-monitor::settings.alerts._built-in-row', ['b' => $b, 'editing' => $editing])
                @endforeach
            </tbody>
        </table>
    </div>
</div>
