@extends('jobs-monitor::layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('jobs-monitor.dashboard', request()->only(['period', 'search', 'page'])) }}"
           class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
            &larr; Back to Dashboard
        </a>
    </div>

    @if(session('status'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-semibold text-gray-900">
                    {{ class_basename($record->jobClass) }}
                </h1>
                @include('jobs-monitor::partials.status-badge', ['value' => $record->status()->value])
            </div>

            <div class="flex items-center gap-2">
                @if($retryEnabled && $canRetry)
                    <form method="POST"
                          action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $record->id->value]) }}"
                          class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Retry
                        </button>
                    </form>
                    <a href="{{ route('jobs-monitor.dlq.edit', ['uuid' => $record->id->value]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-md transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Retry with edit
                    </a>
                @elseif(! $retryEnabled)
                    <span class="text-xs text-gray-500 italic">
                        Retry requires <code class="font-mono">store_payload=true</code>
                    </span>
                @endif

                @if($canDelete)
                    <button type="button"
                            onclick="document.getElementById('detail-delete-modal').classList.remove('hidden')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-md transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2" />
                        </svg>
                        Delete
                    </button>
                @endif
            </div>
        </div>

        <div class="px-6 py-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">UUID</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $record->id->value }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Attempt</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->attempt->value }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Job Class</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono break-all">{{ $record->jobClass }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Connection</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->connection }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Queue</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->queue->value }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Duration</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($record->duration())
                            {{ number_format($record->duration()->milliseconds) }}ms
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Started At</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->startedAt->format('Y-m-d H:i:s.u') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Finished At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $record->finishedAt()?->format('Y-m-d H:i:s.u') ?? '—' }}
                    </dd>
                </div>
                @if($record->failureCategory())
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Failure Category</dt>
                        <dd class="mt-1">
                            @include('jobs-monitor::partials.failure-category-badge', [
                                'value' => $record->failureCategory()->value,
                                'label' => $record->failureCategory()->label(),
                            ])
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        @if($record->payload())
            <div class="border-t border-gray-200 px-6 py-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Payload</h2>
                <pre class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-xs text-gray-800 overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ json_encode($record->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif

        @if($record->exception())
            <div class="border-t border-gray-200 px-6 py-5">
                <h2 class="text-sm font-semibold text-red-700 mb-3">Exception</h2>
                <pre class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-900 overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ $record->exception() }}</pre>
            </div>
        @endif

        @if(count($attempts) > 1)
            <div class="border-t border-gray-200 px-6 py-5">
                <div class="flex items-baseline justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Retry Timeline</h2>
                    <span class="text-xs text-gray-500">{{ count($attempts) }} attempts</span>
                </div>
                <ol class="space-y-2">
                    @foreach(array_reverse($attempts) as $att)
                        @php
                            $isCurrent = $att->attempt->value === $currentAttempt;
                            $rowBg = $isCurrent ? 'bg-indigo-50 border-indigo-300' : 'bg-white border-gray-200 hover:border-gray-300';
                        @endphp
                        <li>
                            <a href="{{ route('jobs-monitor.detail', ['uuid' => $att->id->value, 'attempt' => $att->attempt->value]) }}"
                               class="block border {{ $rowBg }} rounded-lg px-4 py-3 transition-colors @if(!$isCurrent) hover:bg-gray-50 @endif">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-700 text-xs font-bold">
                                        #{{ $att->attempt->value }}
                                    </span>
                                    @include('jobs-monitor::partials.status-badge', ['value' => $att->status()->value])
                                    @if($att->failureCategory())
                                        @include('jobs-monitor::partials.failure-category-badge', [
                                            'value' => $att->failureCategory()->value,
                                            'label' => $att->failureCategory()->label(),
                                        ])
                                    @endif
                                    <span class="text-xs text-gray-500 font-mono">{{ $att->startedAt->format('Y-m-d H:i:s') }}</span>
                                    @if($att->duration())
                                        <span class="text-xs text-gray-500">{{ number_format($att->duration()->milliseconds) }}ms</span>
                                    @endif
                                    @if($isCurrent)
                                        <span class="ml-auto text-xs font-semibold text-indigo-700">viewing</span>
                                    @endif
                                </div>
                                @if($att->exception())
                                    <p class="mt-2 text-xs text-red-700 font-mono truncate">{{ \Illuminate\Support\Str::limit($att->exception(), 120) }}</p>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>

    @if($canDelete)
        <div id="detail-delete-modal"
             class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40"
             onclick="if(event.target===this) this.classList.add('hidden')">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Delete this job record?</h3>
                <p class="text-sm text-gray-600 mb-4">
                    This will remove every stored attempt for UUID
                    <code class="font-mono text-xs">{{ $record->id->value }}</code>.
                    The action cannot be undone.
                </p>
                <div class="flex justify-end gap-2">
                    <button type="button"
                            onclick="document.getElementById('detail-delete-modal').classList.add('hidden')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 rounded-md">
                        Cancel
                    </button>
                    <form method="POST"
                          action="{{ route('jobs-monitor.dlq.delete', ['uuid' => $record->id->value]) }}"
                          class="inline">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    document.getElementById('detail-delete-modal')?.classList.add('hidden');
                }
            });
        </script>
    @endif
@endsection
