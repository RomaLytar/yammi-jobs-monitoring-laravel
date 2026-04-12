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
            @include('jobs-monitor::partials.status-badge', ['value' => $record->status()->value])
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
@endsection
