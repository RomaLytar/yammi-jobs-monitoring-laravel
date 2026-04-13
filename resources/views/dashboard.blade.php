@extends('jobs-monitor::layouts.app')

@section('content')
    @php
        $baseParams = array_filter([
            'period' => $vm->period,
            'search' => $vm->search,
            'status' => $vm->status,
            'queue' => $vm->queue,
            'connection' => $vm->connection,
            'failure_category' => $vm->failureCategory,
        ]);

        $statusOptions = [
            '' => 'All statuses',
            'processing' => 'Processing',
            'processed' => 'Processed',
            'failed' => 'Failed',
        ];

        $failureCategoryOptions = [
            '' => 'All categories',
            'transient' => 'Transient',
            'permanent' => 'Permanent',
            'critical' => 'Critical',
            'unknown' => 'Unknown',
        ];

        $activeFilters = [
            ['key' => 'search', 'name' => 'Search', 'value' => $vm->search],
            ['key' => 'status', 'name' => 'Status', 'value' => $vm->status],
            ['key' => 'queue', 'name' => 'Queue', 'value' => $vm->queue],
            ['key' => 'connection', 'name' => 'Connection', 'value' => $vm->connection],
            ['key' => 'failure_category', 'name' => 'Category', 'value' => $vm->failureCategory],
        ];
        $activeFilters = array_values(array_filter($activeFilters, static fn (array $f) => $f['value'] !== ''));

        // Tailwind (CDN) doesn't include arbitrary-value backgrounds; build the chevron inline
        $chevron = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 20 20\' fill=\'%236b7280\'%3E%3Cpath fill-rule=\'evenodd\' d=\'M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z\' clip-rule=\'evenodd\' /%3E%3C/svg%3E")';

        $selectBase = 'appearance-none bg-no-repeat pr-8 pl-3 py-1.5 text-sm rounded-md border transition-shadow focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer';
        $selectIdle = 'border-gray-300 bg-white text-gray-700 hover:border-gray-400';
        $selectActive = 'border-indigo-500 ring-2 ring-indigo-200 bg-indigo-50 text-indigo-800 font-medium';
        $selectStyle = 'background-image: '.$chevron.'; background-position: right 0.6rem center; background-size: 1rem;';
    @endphp

    {{-- Filters bar --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6 space-y-3">
        {{-- Period pills (carry active filters forward) --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap gap-1">
                @foreach($vm->periods as $key => $label)
                    <a href="{{ route('jobs-monitor.dashboard', array_merge($baseParams, ['period' => $key])) }}"
                       class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                              {{ $vm->period === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $key }}
                    </a>
                @endforeach
            </div>
            <a href="{{ url()->full() }}" class="ml-auto bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-3 py-1.5 text-xs font-medium rounded-md transition-colors flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                Refresh
            </a>
        </div>

        {{-- Filters form --}}
        <form method="GET" action="{{ route('jobs-monitor.dashboard') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="period" value="{{ $vm->period }}">

            <input type="text" name="search" value="{{ $vm->search }}" placeholder="Search by job class..."
                   class="border {{ $vm->search !== '' ? 'border-indigo-500 ring-2 ring-indigo-200 bg-indigo-50' : 'border-gray-300' }} rounded-md px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

            <select name="status"
                    class="{{ $selectBase }} {{ $vm->status !== '' ? $selectActive : $selectIdle }}"
                    style="{{ $selectStyle }}">
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($vm->status === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="queue"
                    class="{{ $selectBase }} {{ $vm->queue !== '' ? $selectActive : $selectIdle }}"
                    style="{{ $selectStyle }}">
                <option value="">All queues</option>
                @foreach($vm->availableQueues as $q)
                    <option value="{{ $q }}" @selected($vm->queue === $q)>{{ $q }}</option>
                @endforeach
            </select>

            <select name="connection"
                    class="{{ $selectBase }} {{ $vm->connection !== '' ? $selectActive : $selectIdle }}"
                    style="{{ $selectStyle }}">
                <option value="">All connections</option>
                @foreach($vm->availableConnections as $c)
                    <option value="{{ $c }}" @selected($vm->connection === $c)>{{ $c }}</option>
                @endforeach
            </select>

            <select name="failure_category"
                    class="{{ $selectBase }} {{ $vm->failureCategory !== '' ? $selectActive : $selectIdle }}"
                    style="{{ $selectStyle }}">
                @foreach($failureCategoryOptions as $value => $label)
                    <option value="{{ $value }}" @selected($vm->failureCategory === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <button type="submit" class="bg-indigo-600 text-white hover:bg-indigo-700 px-4 py-1.5 text-sm font-medium rounded-md transition-colors">Apply</button>

            @if(count($activeFilters) > 0)
                <a href="{{ route('jobs-monitor.dashboard', ['period' => $vm->period]) }}"
                   class="text-gray-500 hover:text-gray-800 text-xs font-medium px-2 py-1.5">Clear all</a>
            @endif
        </form>

        {{-- Active filter chips — each has its own × to remove individually --}}
        @if(count($activeFilters) > 0)
            <div class="flex flex-wrap items-center gap-2 pt-1">
                @foreach($activeFilters as $filter)
                    @php
                        $removeParams = $baseParams;
                        unset($removeParams[$filter['key']]);
                    @endphp
                    <span class="inline-flex items-center gap-1.5 bg-indigo-100 text-indigo-800 pl-2.5 pr-1 py-1 rounded-md text-xs font-medium">
                        <span>{{ $filter['name'] }}: <span class="font-bold">{{ $filter['value'] }}</span></span>
                        <a href="{{ route('jobs-monitor.dashboard', $removeParams) }}"
                           class="inline-flex items-center justify-center w-4 h-4 rounded-full text-indigo-600 hover:bg-indigo-200 hover:text-indigo-900 transition-colors"
                           title="Remove {{ $filter['name'] }} filter">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Time-series chart --}}
    @include('jobs-monitor::partials.time-series-chart', ['period' => $vm->period])

    {{-- Summary cards (auto-refreshed every 5s) --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Total</div>
            <div class="mt-1 text-3xl font-bold text-gray-900" data-summary-card="total">{{ number_format($vm->statusCounts['total']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Processed</div>
            <div class="mt-1 text-3xl font-bold text-green-600" data-summary-card="processed">{{ number_format($vm->statusCounts['processed']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Failed</div>
            <div class="mt-1 text-3xl font-bold {{ $vm->statusCounts['failed'] > 0 ? 'text-red-600' : 'text-gray-900' }}" data-summary-card="failed">{{ number_format($vm->statusCounts['failed']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Processing</div>
            <div class="mt-1 text-3xl font-bold {{ $vm->statusCounts['processing'] > 0 ? 'text-yellow-600' : 'text-gray-900' }}" data-summary-card="processing">{{ number_format($vm->statusCounts['processing']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Success Rate</div>
            <div class="mt-1 text-3xl font-bold text-gray-900" data-summary-card="success_rate">{{ $vm->successRate() }}</div>
        </div>
    </div>
    @include('jobs-monitor::partials.summary-auto-refresh')

    {{-- ===== FAILED JOBS TABLE ===== --}}
    @if($vm->failuresTotal > 0)
        @php
            $fSortUrl = fn(string $col) => route('jobs-monitor.dashboard', array_merge($baseParams, [
                'page' => $vm->jobsPage, 'sort' => $vm->jobsSort, 'dir' => $vm->jobsDir,
                'fpage' => 1, 'fsort' => $col,
                'fdir' => ($vm->failuresSort === $col && $vm->failuresDir === 'asc') ? 'desc' : 'asc',
            ]));
            $fIcon = fn(string $col) => $vm->failuresSort === $col
                ? ($vm->failuresDir === 'asc' ? ' ↑' : ' ↓')
                : ' ⇅';
            $fSortClass = fn(string $col) => $vm->failuresSort === $col
                ? 'text-red-800 font-bold'
                : 'text-red-600 opacity-80';
        @endphp
        <div class="bg-white rounded-lg shadow-sm border border-red-200 mb-6">
            <div class="px-5 py-4 border-b border-red-200">
                <h2 class="text-base font-semibold text-red-700">Failed Jobs <span class="text-sm font-normal text-red-500">({{ number_format($vm->failuresTotal) }} total)</span></h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-red-50 text-left text-xs font-medium uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3"><a href="{{ $fSortUrl('job_class') }}" class="{{ $fSortClass('job_class') }} hover:text-red-900">Job{{ $fIcon('job_class') }}</a></th>
                            <th class="px-5 py-3 text-red-700">Queue</th>
                            <th class="px-5 py-3 text-red-700">Attempt</th>
                            <th class="px-5 py-3"><a href="{{ $fSortUrl('started_at') }}" class="{{ $fSortClass('started_at') }} hover:text-red-900">Failed At{{ $fIcon('started_at') }}</a></th>
                            <th class="px-5 py-3"><a href="{{ $fSortUrl('duration_ms') }}" class="{{ $fSortClass('duration_ms') }} hover:text-red-900">Duration{{ $fIcon('duration_ms') }}</a></th>
                            <th class="px-5 py-3 text-red-700">Category</th>
                            <th class="px-5 py-3 text-red-700">Exception</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100">
                        @foreach($vm->failures as $job)
                            <tr class="hover:bg-red-50/50 cursor-pointer" onclick="this.nextElementSibling.classList.toggle('hidden')">
                                <td class="px-5 py-3 font-medium text-red-800">{{ $job['short_class'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['queue'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['attempt'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['finished_at'] ?? $job['started_at'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['duration_formatted'] }}</td>
                                <td class="px-5 py-3">
                                    @include('jobs-monitor::partials.failure-category-badge', [
                                        'value' => $job['failure_category'],
                                        'label' => $job['failure_category_label'],
                                    ])
                                </td>
                                <td class="px-5 py-3 text-red-600 truncate max-w-xs" title="{{ $job['exception'] ?? '' }}">{{ \Illuminate\Support\Str::limit($job['exception'] ?? '', 60) }}</td>
                            </tr>
                            <tr class="hidden">
                                <td colspan="7" class="px-5 py-4 bg-red-50/40">
                                    <div class="flex justify-end mb-3">
                                        <a href="{{ route('jobs-monitor.detail', ['uuid' => $job['uuid'], 'attempt' => $job['attempt']]) }}"
                                           class="inline-flex items-center gap-1.5 bg-red-600 text-white hover:bg-red-700 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors">
                                            View details &amp; retry timeline
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                        </a>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">UUID</span><p class="text-sm font-mono text-gray-900">{{ $job['uuid'] }}</p></div>
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">Full Class</span><p class="text-sm font-mono text-gray-900 break-all">{{ $job['job_class'] }}</p></div>
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">Connection</span><p class="text-sm text-gray-900">{{ $job['connection'] }}</p></div>
                                        <div><span class="text-xs font-medium text-gray-500 uppercase">Started At</span><p class="text-sm text-gray-900">{{ $job['started_at'] }}</p></div>
                                    </div>
                                    @if($job['payload'])
                                        <div class="mb-3"><span class="text-xs font-medium text-gray-500 uppercase">Payload</span>
                                            <pre class="mt-1 bg-white border border-gray-200 rounded-lg p-3 text-xs text-gray-800 overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ json_encode($job['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                    @if($job['exception'])
                                        <div><span class="text-xs font-medium text-red-600 uppercase">Exception</span>
                                            <pre class="mt-1 bg-red-100 border border-red-200 rounded-lg p-3 text-xs text-red-900 overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ $job['exception'] }}</pre>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{-- Failed pagination --}}
            @if($vm->failuresLastPage > 1)
                @include('jobs-monitor::partials.pagination', [
                    'currentPage' => $vm->failuresPage,
                    'lastPage' => $vm->failuresLastPage,
                    'pageParam' => 'fpage',
                    'extraParams' => array_merge($baseParams, [
                        'page' => $vm->jobsPage, 'sort' => $vm->jobsSort, 'dir' => $vm->jobsDir,
                        'fsort' => $vm->failuresSort, 'fdir' => $vm->failuresDir,
                    ]),
                ])
            @endif
        </div>
    @endif

    {{-- ===== ALL JOBS TABLE ===== --}}
    @php
        $jSortUrl = fn(string $col) => route('jobs-monitor.dashboard', array_merge($baseParams, [
            'page' => 1, 'sort' => $col,
            'dir' => ($vm->jobsSort === $col && $vm->jobsDir === 'asc') ? 'desc' : 'asc',
            'fpage' => $vm->failuresPage, 'fsort' => $vm->failuresSort, 'fdir' => $vm->failuresDir,
        ]));
        $jIcon = fn(string $col) => $vm->jobsSort === $col
            ? ($vm->jobsDir === 'asc' ? ' ↑' : ' ↓')
            : ' ⇅';
        $jSortClass = fn(string $col) => $vm->jobsSort === $col
            ? 'text-gray-900 font-bold'
            : 'text-gray-500 opacity-80';
    @endphp
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">All Jobs <span class="text-sm font-normal text-gray-500">({{ number_format($vm->jobsTotal) }} total)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3"><a href="{{ $jSortUrl('status') }}" class="{{ $jSortClass('status') }} hover:text-gray-900">Status{{ $jIcon('status') }}</a></th>
                        <th class="px-5 py-3"><a href="{{ $jSortUrl('job_class') }}" class="{{ $jSortClass('job_class') }} hover:text-gray-900">Job{{ $jIcon('job_class') }}</a></th>
                        <th class="px-5 py-3 text-gray-500">Connection</th>
                        <th class="px-5 py-3 text-gray-500">Queue</th>
                        <th class="px-5 py-3 text-gray-500">Attempt</th>
                        <th class="px-5 py-3"><a href="{{ $jSortUrl('started_at') }}" class="{{ $jSortClass('started_at') }} hover:text-gray-900">Started At{{ $jIcon('started_at') }}</a></th>
                        <th class="px-5 py-3"><a href="{{ $jSortUrl('duration_ms') }}" class="{{ $jSortClass('duration_ms') }} hover:text-gray-900">Duration{{ $jIcon('duration_ms') }}</a></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($vm->jobs as $job)
                        <tr class="{{ $job['is_failed'] ? 'bg-red-50/30 hover:bg-red-50/60' : 'hover:bg-gray-50/50' }} cursor-pointer" onclick="this.nextElementSibling.classList.toggle('hidden')">
                            <td class="px-5 py-3">
                                @include('jobs-monitor::partials.status-badge', ['value' => $job['status']])
                            </td>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $job['short_class'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['connection'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['queue'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['attempt'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['started_at'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['duration_formatted'] }}</td>
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
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div><span class="text-xs font-medium text-gray-500 uppercase">UUID</span><p class="text-sm font-mono text-gray-900">{{ $job['uuid'] }}</p></div>
                                    <div><span class="text-xs font-medium text-gray-500 uppercase">Full Class</span><p class="text-sm font-mono text-gray-900 break-all">{{ $job['job_class'] }}</p></div>
                                    <div><span class="text-xs font-medium text-gray-500 uppercase">Finished At</span><p class="text-sm text-gray-900">{{ $job['finished_at'] ?? '—' }}</p></div>
                                </div>
                                @if($job['failure_category'])
                                    <div class="mt-3">
                                        <span class="text-xs font-medium text-gray-500 uppercase">Failure Category</span>
                                        <p class="text-sm mt-1">
                                            @include('jobs-monitor::partials.failure-category-badge', [
                                                'value' => $job['failure_category'],
                                                'label' => $job['failure_category_label'],
                                            ])
                                        </p>
                                    </div>
                                @endif
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
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No jobs found for the selected period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{-- All jobs pagination --}}
        @if($vm->jobsLastPage > 1)
            @include('jobs-monitor::partials.pagination', [
                'currentPage' => $vm->jobsPage,
                'lastPage' => $vm->jobsLastPage,
                'pageParam' => 'page',
                'extraParams' => array_merge($baseParams, [
                    'sort' => $vm->jobsSort, 'dir' => $vm->jobsDir,
                    'fpage' => $vm->failuresPage, 'fsort' => $vm->failuresSort, 'fdir' => $vm->failuresDir,
                ]),
            ])
        @endif
    </div>
@endsection
