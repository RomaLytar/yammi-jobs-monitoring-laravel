@extends('jobs-monitor::layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900">Dead Letter Queue</h1>
        <p class="text-sm text-gray-500 mt-1">
            Jobs that exhausted all retries (attempt &ge; {{ $vm->maxTries }}) or failed with a permanent / critical category.
        </p>
    </div>

    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-md px-4 py-3 text-sm mb-4">
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-md px-4 py-3 text-sm mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if(! $vm->retryEnabled)
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-900 rounded-md px-4 py-3 text-sm mb-4">
            Retry is disabled because payloads are not stored.
            Set <code class="font-mono">JOBS_MONITOR_STORE_PAYLOAD=true</code> in the host app to enable re-dispatch.
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-baseline justify-between">
            <h2 class="text-base font-semibold text-gray-900">
                {{ number_format($vm->total) }} dead {{ $vm->total === 1 ? 'entry' : 'entries' }}
            </h2>
        </div>

        @if(count($vm->jobs) === 0)
            <div class="px-5 py-10 text-center text-gray-400 text-sm">
                No dead-letter jobs. Great — everything eventually succeeded or is still retryable.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Job</th>
                            <th class="px-5 py-3">Queue</th>
                            <th class="px-5 py-3">Attempts</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3">Last failed</th>
                            <th class="px-5 py-3">Exception</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($vm->jobs as $job)
                            <tr class="hover:bg-gray-50/50 cursor-pointer" onclick="this.nextElementSibling.classList.toggle('hidden')">
                                <td class="px-5 py-3 font-medium text-gray-900" title="{{ $job['job_class'] }}">{{ $job['short_class'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['queue'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['attempt'] }}</td>
                                <td class="px-5 py-3">
                                    @include('jobs-monitor::partials.failure-category-badge', [
                                        'value' => $job['failure_category'],
                                        'label' => $job['failure_category_label'],
                                    ])
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['finished_at'] ?? $job['started_at'] }}</td>
                                <td class="px-5 py-3 text-red-600 truncate max-w-xs" title="{{ $job['exception'] ?? '' }}">{{ \Illuminate\Support\Str::limit($job['exception'] ?? '', 50) }}</td>
                                <td class="px-5 py-3 text-right" onclick="event.stopPropagation()">
                                    <div class="inline-flex items-center gap-2">
                                        @if($vm->retryEnabled && $job['has_payload'])
                                            <form method="POST" action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $job['uuid']]) }}">
                                                @csrf
                                                <button type="submit" class="bg-indigo-600 text-white hover:bg-indigo-700 px-3 py-1 text-xs font-semibold rounded-md transition-colors">Retry</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('jobs-monitor.dlq.delete', ['uuid' => $job['uuid']]) }}"
                                              onsubmit="return confirm('Delete this dead-letter entry? All attempts for this UUID will be removed.');">
                                            @csrf
                                            <button type="submit" class="bg-red-50 text-red-700 hover:bg-red-100 px-3 py-1 text-xs font-semibold rounded-md transition-colors">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hidden">
                                <td colspan="7" class="px-5 py-4 bg-gray-50/50">
                                    <div class="flex justify-end mb-3">
                                        <a href="{{ route('jobs-monitor.detail', ['uuid' => $job['uuid'], 'attempt' => $job['attempt']]) }}"
                                           class="inline-flex items-center gap-1.5 bg-indigo-600 text-white hover:bg-indigo-700 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors">
                                            View details &amp; retry timeline
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                        </a>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">UUID</span><p class="text-sm font-mono text-gray-900">{{ $job['uuid'] }}</p></div>
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">Full class</span><p class="text-sm font-mono text-gray-900 break-all">{{ $job['job_class'] }}</p></div>
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">Connection</span><p class="text-sm text-gray-900">{{ $job['connection'] }}</p></div>
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">Started at</span><p class="text-sm text-gray-900">{{ $job['started_at'] }}</p></div>
                                    </div>
                                    @if($job['payload'])
                                        <div class="mt-3"><span class="text-xs font-medium text-gray-500 uppercase">Payload</span>
                                            <pre class="mt-1 bg-white border border-gray-200 rounded-lg p-3 text-xs text-gray-800 overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ json_encode($job['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                    @if($job['exception'])
                                        <div class="mt-3"><span class="text-xs font-medium text-red-600 uppercase">Exception</span>
                                            <pre class="mt-1 bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-900 overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ $job['exception'] }}</pre>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($vm->lastPage > 1)
                @include('jobs-monitor::partials.pagination', [
                    'currentPage' => $vm->page,
                    'lastPage' => $vm->lastPage,
                    'pageParam' => 'page',
                    'extraParams' => [],
                ])
            @endif
        @endif
    </div>
@endsection
