@php
    $sourceNameIsAuto = $alerts->sourceNameSource->value === 'auto';
    $monitorUrlIsAuto = $alerts->monitorUrlSource->value === 'auto';
    $input = 'block w-full h-9 rounded-md border border-input bg-card px-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring transition-[box-shadow,border-color]';
@endphp

<form method="POST" action="{{ route('jobs-monitor.settings.alerts.update') }}"
      class="rounded-xl border border-border bg-card text-card-foreground p-5 space-y-5 shadow-xs">
    @csrf

    <div class="flex items-start gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
            <i data-lucide="fingerprint" class="text-[16px]"></i>
        </span>
        <div>
            <h2 class="text-base font-semibold tracking-tight">Identification</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Used in alert messages and "Open dashboard" links so a single Slack channel can distinguish environments.
            </p>
        </div>
    </div>

    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <label for="source_name" class="text-sm font-medium">Source name</label>
            @include('jobs-monitor::settings.partials.tooltip', [
                'text' => 'Short label that identifies this environment in alert messages — e.g. "Production" or "Staging". Shown in the Slack header and email subject so a single channel can distinguish environments. Auto-derived from app.name (+ env) when not set here.',
            ])
            @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->sourceNameSource->value])
        </div>
        <input type="text" name="source_name" id="source_name"
               value="{{ old('source_name', $sourceNameIsAuto ? '' : $alerts->sourceName) }}"
               maxlength="100"
               placeholder="{{ $sourceNameIsAuto ? $alerts->sourceName : 'e.g. Production' }}"
               class="{{ $input }}">
        @if($sourceNameIsAuto)
            <p class="mt-1.5 text-xs text-muted-foreground">
                Auto-derived from <code class="bg-muted px-1 py-0.5 rounded text-[11px]">app.name</code>. Override only if you want a different label.
            </p>
        @endif
        @error('source_name')
            <p class="mt-1.5 text-xs text-destructive">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <label for="monitor_url" class="text-sm font-medium">Monitor URL</label>
            @include('jobs-monitor::settings.partials.tooltip', [
                'text' => 'Base URL where this monitor is reachable. Used to render "Open dashboard" / "Open DLQ" deep-link buttons inside alert messages so on-call can jump to the failing job in one click. Auto-derived from app.url + ui.path when not set here.',
            ])
            @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->monitorUrlSource->value])
        </div>
        <input type="url" name="monitor_url" id="monitor_url"
               value="{{ old('monitor_url', $monitorUrlIsAuto ? '' : $alerts->monitorUrl) }}"
               maxlength="500"
               placeholder="{{ $monitorUrlIsAuto ? $alerts->monitorUrl : 'https://monitor.example.com' }}"
               class="{{ $input }}">
        @if($monitorUrlIsAuto)
            <p class="mt-1.5 text-xs text-muted-foreground">
                Auto-derived from <code class="bg-muted px-1 py-0.5 rounded text-[11px]">app.url</code> + <code class="bg-muted px-1 py-0.5 rounded text-[11px]">ui.path</code>. Override only if the monitor lives on a different host.
            </p>
        @endif
        @error('monitor_url')
            <p class="mt-1.5 text-xs text-destructive">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex justify-end pt-2 border-t border-border">
        <button type="submit"
                class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs transition-colors">
            <i data-lucide="save" class="text-[14px]"></i>
            Save settings
        </button>
    </div>
</form>
