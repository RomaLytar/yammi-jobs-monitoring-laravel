@extends('jobs-monitor::layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('jobs-monitor.dlq') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
            <i data-lucide="arrow-left" class="text-[14px]"></i>
            Back to DLQ
        </a>
    </div>

    <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs">
        <div class="px-6 py-4 border-b border-border flex items-start gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="file-json-2" class="text-[16px]"></i>
            </span>
            <div>
                <h1 class="text-lg font-semibold tracking-tight">Edit payload and retry</h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Fix the data, then re-dispatch. The retried job gets a new UUID and shows up as a fresh run in the dashboard.
                </p>
            </div>
        </div>

        @if(session('error'))
            <div class="mx-6 mt-4 flex items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 text-destructive px-4 py-3 text-sm">
                <i data-lucide="alert-circle" class="text-[16px] mt-0.5"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        @if(! $retryEnabled)
            <div class="mx-6 mt-4 flex items-start gap-3 rounded-lg border border-warning/25 bg-warning/10 text-warning px-4 py-3 text-sm">
                <i data-lucide="alert-triangle" class="text-[16px] mt-0.5"></i>
                <div>
                    Retry is disabled because payloads are not stored.
                    Set <code class="px-1.5 py-0.5 rounded bg-card border border-border text-xs font-mono">JOBS_MONITOR_STORE_PAYLOAD=true</code> in the host app to enable re-dispatch.
                </div>
            </div>
        @endif

        <div class="px-6 py-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 mb-5">
                @foreach([
                    ['Job class',  $jobClass,   true],
                    ['UUID (original)', $uuid,  true],
                    ['Connection', $connection, false],
                    ['Queue',      $queue,      false],
                ] as [$label, $val, $mono])
                    <div>
                        <dt class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">{{ $label }}</dt>
                        <dd class="mt-1 text-sm {{ $mono ? 'font-mono break-all' : '' }}">{{ $val }}</dd>
                    </div>
                @endforeach
            </dl>

            @if($payload === null)
                <div class="flex items-start gap-3 rounded-lg border border-warning/25 bg-warning/10 text-warning px-4 py-3 text-sm">
                    <i data-lucide="alert-triangle" class="text-[16px] mt-0.5"></i>
                    <div>This job has no stored payload. Nothing to edit.</div>
                </div>
            @else
                @php
                    $editValue = $previousInput ?? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                @endphp
                <form method="POST" action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $uuid]) }}">
                    @csrf
                    <label for="payload" class="block text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-2">Payload (JSON)</label>
                    <textarea id="payload" name="payload" rows="20" spellcheck="false"
                              class="w-full rounded-md border border-input bg-card px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring disabled:opacity-60"
                              @if(! $retryEnabled) disabled @endif>{{ $editValue }}</textarea>

                    <p class="mt-2 text-xs text-muted-foreground">
                        The <code class="font-mono px-1 py-0.5 bg-muted rounded">uuid</code> field is overwritten automatically on dispatch. Everything else you edit is kept.
                    </p>

                    <div class="mt-4 flex items-center gap-2">
                        <button type="submit"
                                @if(! $retryEnabled) disabled @endif
                                class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-md bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-xs">
                            <i data-lucide="send" class="text-[14px]"></i>
                            Retry with this payload
                        </button>
                        <a href="{{ route('jobs-monitor.dlq') }}"
                           class="inline-flex items-center h-9 px-3 text-sm font-medium text-muted-foreground hover:text-foreground">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
