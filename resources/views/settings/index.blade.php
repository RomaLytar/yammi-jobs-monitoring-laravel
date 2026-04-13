@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
        <p class="mt-1 text-sm text-gray-600">
            Configure feature toggles. Database values override config; secrets stay in config only.
        </p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach($vm->features as $feature)
            @include('jobs-monitor::settings.partials.feature-card', ['feature' => $feature])
        @endforeach
    </div>
</div>
@endsection
