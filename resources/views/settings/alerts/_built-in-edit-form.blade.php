@php
    $input = 'block w-full h-9 rounded-md border border-input bg-card px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring transition-[box-shadow,border-color]';
@endphp

<form method="POST"
      action="{{ route('jobs-monitor.settings.alerts.built-in.update', ['key' => $b->key]) }}"
      class="space-y-4 border border-brand/30 ring-2 ring-brand/15 rounded-lg bg-card p-4 shadow-xs animate-slide-down">
    @csrf

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-md bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="pencil" class="text-[14px]"></i>
            </span>
            <div>
                <h3 class="text-sm font-semibold">Edit {{ $b->key }}</h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ $b->trigger->label() }}@if($b->triggerValue !== null): <code class="text-[11px] bg-muted px-1 py-0.5 rounded">{{ $b->triggerValue }}</code>@endif
                    · Trigger and identifier are fixed to the shipped built-in.
                </p>
            </div>
        </div>
        <a href="{{ route('jobs-monitor.settings.alerts') }}" class="text-xs text-muted-foreground hover:text-foreground">Cancel</a>
    </div>

    @if($errors->any())
        <div class="flex items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-xs text-destructive">
            <i data-lucide="alert-circle" class="text-[14px] mt-0.5"></i>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-3 gap-3">
        <div>
            <label for="threshold-{{ $b->key }}" class="block text-xs font-medium mb-1">Threshold</label>
            <input type="number" id="threshold-{{ $b->key }}" name="threshold" min="1"
                   value="{{ old('threshold', $b->threshold) }}" class="{{ $input }} tabular-nums">
        </div>
        <div>
            <label for="window-{{ $b->key }}" class="block text-xs font-medium mb-1">Window</label>
            <input type="text" id="window-{{ $b->key }}" name="window" maxlength="16"
                   value="{{ old('window', $b->window) }}"
                   placeholder="5m, 1h, 2d" class="{{ $input }}">
        </div>
        <div>
            <label for="cooldown-{{ $b->key }}" class="block text-xs font-medium mb-1">Cooldown (min)</label>
            <input type="number" id="cooldown-{{ $b->key }}" name="cooldown_minutes" min="1"
                   value="{{ old('cooldown_minutes', $b->cooldownMinutes) }}" class="{{ $input }} tabular-nums">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="min-attempt-{{ $b->key }}" class="block text-xs font-medium mb-1">Min attempt</label>
            <input type="number" id="min-attempt-{{ $b->key }}" name="min_attempt" min="1"
                   value="{{ old('min_attempt', $b->minAttempt) }}"
                   placeholder="any" class="{{ $input }} tabular-nums">
            <p class="mt-1 text-xs text-muted-foreground">Silence first-try noise (e.g. 2 = only retries).</p>
        </div>
        <div>
            <span class="block text-xs font-medium mb-1">Channels</span>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-2">
                @php
                    $chs = old('channels', $b->channels);
                    // Data-driven catalog — single source used here and in
                    // _built-in-row badges. Adding a channel means one entry
                    // in this map, nothing else.
                    $channelCatalog = [
                        'slack' => ['label' => 'Slack', 'icon' => 'slack'],
                        'mail' => ['label' => 'Mail', 'icon' => 'mail'],
                        'pagerduty' => ['label' => 'PagerDuty', 'icon' => 'siren'],
                        'opsgenie' => ['label' => 'Opsgenie', 'icon' => 'shield-alert'],
                        'webhook' => ['label' => 'Webhook', 'icon' => 'webhook'],
                    ];
                @endphp
                @foreach($channelCatalog as $value => $meta)
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="channels[]" value="{{ $value }}"
                               {{ in_array($value, (array) $chs, true) ? 'checked' : '' }}
                               class="h-4 w-4 rounded border-input bg-card text-brand focus:ring-2 focus:ring-ring focus:ring-offset-0">
                        <span class="inline-flex items-center gap-1 text-sm">
                            <i data-lucide="{{ $meta['icon'] }}" class="text-[13px] text-muted-foreground"></i>
                            {{ $meta['label'] }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between pt-3 border-t border-border">
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1"
                   {{ old('enabled', $b->effectivelyEnabled) ? 'checked' : '' }}
                   class="h-4 w-4 rounded border-input bg-card text-brand focus:ring-2 focus:ring-ring focus:ring-offset-0">
            <span class="text-sm font-medium">Enabled</span>
        </label>

        <div class="flex items-center gap-2">
            <a href="{{ route('jobs-monitor.settings.alerts') }}"
               class="inline-flex items-center h-9 px-3 text-sm font-medium rounded-md bg-card text-foreground border border-border hover:bg-accent hover:text-accent-foreground transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs transition-colors">
                <i data-lucide="save" class="text-[14px]"></i>
                Save changes
            </button>
        </div>
    </div>
</form>
