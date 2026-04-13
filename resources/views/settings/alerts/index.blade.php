@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="bell-ring" class="text-[18px]"></i>
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Alerts</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Proactive notifications when failure thresholds are crossed.
                </p>
            </div>
        </div>
        <a href="{{ route('jobs-monitor.settings') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
            <i data-lucide="arrow-left" class="text-[14px]"></i>
            Back to settings
        </a>
    </div>

    @if(session('jobs_monitor_status'))
        <div class="flex items-start gap-3 rounded-lg border border-success/25 bg-success/10 text-success px-4 py-3 text-sm">
            <i data-lucide="check-circle-2" class="text-[16px] mt-0.5"></i>
            <div>{{ session('jobs_monitor_status') }}</div>
        </div>
    @endif

    @include('jobs-monitor::settings.alerts._toggle', ['alerts' => $alerts])

    @if($alerts->enabled)
        @include('jobs-monitor::settings.alerts._scalars', ['alerts' => $alerts])
        @include('jobs-monitor::settings.alerts._recipients', ['alerts' => $alerts])
        @include('jobs-monitor::settings.alerts._rules', [
            'rulesOverview' => $rulesOverview,
            'editing' => $editing,
        ])
    @else
        <div class="rounded-xl border border-border bg-card px-6 py-10 text-center">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                <i data-lucide="bell-off" class="text-xl"></i>
            </div>
            <p class="text-sm font-medium">Alerts are currently disabled</p>
            <p class="text-xs text-muted-foreground mt-1">Enable them above to configure source name, monitor URL, recipients and rules.</p>
        </div>
    @endif
</div>
@endsection
