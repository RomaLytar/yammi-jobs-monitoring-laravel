@extends('jobs-monitor::layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('jobs-monitor.dashboard', request()->only(['period', 'search', 'page'])) }}"
           class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
            &larr; Back to Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-900">
                {{ class_basename($record->jobClass) }}
            </h1>
            @if($record->status()->value === 'processed')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                    processed
                </span>
            @elseif($record->status()->value === 'failed')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                    failed
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                    processing
                </span>
            @endif
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

        @if($record->exception())
            <div class="border-t border-gray-200 px-6 py-5">
                <h2 class="text-sm font-semibold text-red-700 mb-3">Exception</h2>
                <pre class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-900 overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ $record->exception() }}</pre>
            </div>
        @endif
    </div>
@endsection
