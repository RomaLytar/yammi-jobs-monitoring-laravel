@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Alerts</h1>
            <p class="mt-1 text-sm text-gray-600">
                Proactive notifications when failure thresholds are crossed.
            </p>
        </div>
        <a href="{{ route('jobs-monitor.settings') }}"
           class="text-sm text-gray-500 hover:text-gray-700">← Back to settings</a>
    </div>

    @if(session('jobs_monitor_status'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-inset ring-green-600/20">
            {{ session('jobs_monitor_status') }}
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
        <div class="rounded-md bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
            Alerts are currently <strong>disabled</strong>. Enable them above to configure source name, monitor URL, recipients and rules.
        </div>
    @endif
</div>
@endsection
