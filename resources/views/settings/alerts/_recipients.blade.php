@php
    $canEdit = $alerts->recipientsSource->value === 'db';
    $input = 'block w-full rounded-md border border-input bg-card px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring';
@endphp

<div class="rounded-xl border border-border bg-card text-card-foreground p-5 space-y-4 shadow-xs">
    <div class="flex items-start gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-info/10 text-info ring-1 ring-inset ring-info/20">
            <i data-lucide="mail" class="text-[16px]"></i>
        </span>
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold tracking-tight">Mail recipients</h2>
                @include('jobs-monitor::settings.partials.source-badge', ['source' => $alerts->recipientsSource->value])
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Addresses that receive email when an alert rule routed to
                <code class="text-[11px] bg-muted px-1 py-0.5 rounded">mail</code> fires.
            </p>
        </div>
    </div>

    @if(empty($alerts->recipients))
        <div class="rounded-lg border border-dashed border-border bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
            <i data-lucide="inbox" class="text-lg mb-1 inline-block"></i>
            <p>No recipients yet. Add one below.</p>
        </div>
    @else
        <ul class="divide-y divide-border border border-border rounded-lg bg-card overflow-hidden">
            @foreach($alerts->recipients as $email)
                <li class="flex items-center justify-between px-3 py-2 hover:bg-muted/40 transition-colors">
                    <span class="flex items-center gap-2 text-sm">
                        <i data-lucide="at-sign" class="text-[13px] text-muted-foreground"></i>
                        {{ $email }}
                    </span>
                    @if($canEdit)
                        <form method="POST"
                              action="{{ route('jobs-monitor.settings.alerts.recipients.delete', ['email' => $email]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center gap-1 text-xs text-destructive hover:bg-destructive/10 px-2 py-1 rounded-md transition-colors">
                                <i data-lucide="x" class="text-[12px]"></i>
                                Remove
                            </button>
                        </form>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <i data-lucide="lock" class="text-[12px]"></i>
                            read-only
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('jobs-monitor.settings.alerts.recipients.add') }}"
          class="space-y-2 pt-3 border-t border-border">
        @csrf
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <label for="email" class="text-sm font-medium">Add recipients</label>
                @include('jobs-monitor::settings.partials.tooltip', [
                    'text' => 'One email per recipient. Add multiple at once by separating with comma, semicolon, space or newline. Each is validated and de-duplicated against the existing list before saving.',
                ])
            </div>
            <textarea name="email" id="email"
                      rows="2"
                      placeholder="ops@example.com, sre@example.com"
                      class="{{ $input }}"
                      required>{{ old('email') }}</textarea>
            <p class="mt-1 text-xs text-muted-foreground">Multiple addresses allowed — separate with comma, semicolon, space or newline.</p>
            @error('email')   <p class="mt-1.5 text-xs text-destructive">{{ $message }}</p> @enderror
            @error('emails')  <p class="mt-1.5 text-xs text-destructive">{{ $message }}</p> @enderror
            @error('emails.*')<p class="mt-1.5 text-xs text-destructive">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs transition-colors">
                <i data-lucide="plus" class="text-[14px]"></i>
                Add recipients
            </button>
        </div>
    </form>
</div>
