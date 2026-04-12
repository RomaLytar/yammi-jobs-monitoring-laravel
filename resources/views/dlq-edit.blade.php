@extends('jobs-monitor::layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('jobs-monitor.dlq') }}"
           class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
            &larr; Back to DLQ
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-lg font-semibold text-gray-900">Edit payload and retry</h1>
            <p class="text-sm text-gray-500 mt-1">
                Fix the data, then re-dispatch. The retried job gets a new UUID and shows up as a fresh run in the dashboard.
            </p>
        </div>

        @if(session('error'))
            <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-800 rounded-md px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if(! $retryEnabled)
            <div class="mx-6 mt-4 bg-yellow-50 border border-yellow-200 text-yellow-900 rounded-md px-4 py-3 text-sm">
                Retry is disabled because payloads are not stored.
                Set <code class="font-mono">JOBS_MONITOR_STORE_PAYLOAD=true</code> in the host app to enable re-dispatch.
            </div>
        @endif

        <div class="px-6 py-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 mb-5">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Job class</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono break-all">{{ $jobClass }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">UUID (original)</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono break-all">{{ $uuid }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Connection</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $connection }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Queue</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $queue }}</dd>
                </div>
            </dl>

            @if($payload === null)
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-900 rounded-md px-4 py-3 text-sm">
                    This job has no stored payload. Nothing to edit.
                </div>
            @else
                @php
                    $editValue = $previousInput ?? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                @endphp
                <form method="POST" action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $uuid]) }}">
                    @csrf
                    <label for="payload" class="block text-xs font-medium text-gray-500 uppercase mb-2">Payload (JSON)</label>
                    <textarea id="payload" name="payload" rows="20" spellcheck="false"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 text-xs font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                              @if(! $retryEnabled) disabled @endif>{{ $editValue }}</textarea>

                    <p class="mt-2 text-xs text-gray-500">
                        The <code class="font-mono">uuid</code> field is overwritten automatically on dispatch. Everything else you edit is kept.
                    </p>

                    <div class="mt-4 flex items-center gap-2">
                        <button type="submit"
                                @if(! $retryEnabled) disabled @endif
                                class="bg-indigo-600 text-white hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed px-4 py-2 text-sm font-semibold rounded-md transition-colors">
                            Retry with this payload
                        </button>
                        <a href="{{ route('jobs-monitor.dlq') }}"
                           class="text-gray-600 hover:text-gray-900 text-sm font-medium px-3 py-2">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
