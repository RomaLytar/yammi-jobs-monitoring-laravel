@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
                <i data-lucide="cpu" class="text-brand text-[22px]"></i>
                Workers
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Live heartbeat view. Silent after {{ $vm->silentAfterSeconds }}s of no pulse.
                Auto-refreshes every {{ $vm->silentAfterSeconds }}s.
            </p>
        </div>
    </div>

    <div id="workers-live" class="space-y-6">
        @include('jobs-monitor::partials.workers-content')
    </div>
</div>

@include('jobs-monitor::partials.kebab-script')
@include('jobs-monitor::partials.workers-auto-refresh')
@endsection
