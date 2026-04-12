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
                                    <div class="relative inline-block text-left" data-dlq-menu>
                                        <button type="button"
                                                class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                title="Actions"
                                                onclick="toggleDlqMenu(this)">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z"/>
                                            </svg>
                                        </button>
                                        <div class="hidden absolute right-0 z-10 mt-1 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                             data-dlq-menu-dropdown>
                                            <div class="py-1">
                                                @if($vm->retryEnabled && $job['has_payload'])
                                                    <form method="POST" action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $job['uuid']]) }}" class="block">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                            Retry
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('jobs-monitor.dlq.edit', ['uuid' => $job['uuid']]) }}"
                                                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        Edit &amp; retry
                                                    </a>
                                                @endif
                                                <a href="{{ route('jobs-monitor.detail', ['uuid' => $job['uuid'], 'attempt' => $job['attempt']]) }}"
                                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    View details
                                                </a>
                                                <div class="h-px bg-gray-100 my-1"></div>
                                                <button type="button"
                                                        class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                                                        onclick="openDlqDeleteConfirm('{{ $job['uuid'] }}', '{{ $job['short_class'] }}', '{{ $job['attempt'] }}')">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
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

    {{-- Delete confirmation modal --}}
    <div id="dlq-delete-modal"
         class="hidden fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="dlq-delete-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center px-4">
            <div class="fixed inset-0 bg-gray-500/60 transition-opacity" onclick="closeDlqDeleteConfirm()"></div>

            <div class="relative w-full max-w-md transform overflow-hidden rounded-lg bg-white shadow-xl transition-all">
                <div class="px-6 pt-5 pb-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 id="dlq-delete-title" class="text-base font-semibold text-gray-900">Delete dead-letter entry?</h3>
                            <p class="mt-2 text-sm text-gray-600">
                                You're about to remove <span id="dlq-delete-job" class="font-mono text-gray-900"></span>
                                and all <span id="dlq-delete-attempts" class="font-semibold"></span> of its stored attempts.
                                This can't be undone.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end gap-2">
                    <button type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                            onclick="closeDlqDeleteConfirm()">Cancel</button>
                    <form id="dlq-delete-form" method="POST" action="">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDlqMenu(button) {
            const dropdown = button.nextElementSibling;
            // Close all other open menus first
            document.querySelectorAll('[data-dlq-menu-dropdown]').forEach(d => {
                if (d !== dropdown) d.classList.add('hidden');
            });
            dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', function (e) {
            document.querySelectorAll('[data-dlq-menu]').forEach(menu => {
                if (!menu.contains(e.target)) {
                    menu.querySelector('[data-dlq-menu-dropdown]')?.classList.add('hidden');
                }
            });
        });

        function openDlqDeleteConfirm(uuid, jobClass, attempts) {
            const modal = document.getElementById('dlq-delete-modal');
            const form = document.getElementById('dlq-delete-form');
            document.getElementById('dlq-delete-job').textContent = jobClass;
            document.getElementById('dlq-delete-attempts').textContent = attempts + (attempts === '1' ? ' attempt' : ' attempts');
            form.action = '{{ url(config('jobs-monitor.ui.path', 'jobs-monitor').'/dlq') }}/' + uuid + '/delete';
            modal.classList.remove('hidden');
            // Close any open menus
            document.querySelectorAll('[data-dlq-menu-dropdown]').forEach(d => d.classList.add('hidden'));
        }

        function closeDlqDeleteConfirm() {
            document.getElementById('dlq-delete-modal').classList.add('hidden');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDlqDeleteConfirm();
        });
    </script>
@endsection
