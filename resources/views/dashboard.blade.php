@extends('jobs-monitor::layouts.app')

@section('content')
    @php
        $baseParams = array_filter(['period' => $vm->period, 'search' => $vm->search]);
    @endphp

    {{-- Filters bar --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap gap-1">
                @foreach($vm->periods as $key => $label)
                    <a href="{{ route('jobs-monitor.dashboard', array_filter(['period' => $key, 'search' => $vm->search])) }}"
                       class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                              {{ $vm->period === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $key }}
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('jobs-monitor.dashboard') }}" class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="period" value="{{ $vm->period }}">
                <input type="text" name="search" value="{{ $vm->search }}" placeholder="Search by job class..."
                       class="border {{ $vm->search !== '' ? 'border-indigo-500 ring-2 ring-indigo-200 bg-indigo-50' : 'border-gray-300' }} rounded-md px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" class="bg-gray-100 text-gray-600 hover:bg-gray-200 px-3 py-1.5 text-xs font-medium rounded-md transition-colors">Search</button>
            </form>
            @if($vm->search !== '')
                <div class="flex items-center gap-1.5 bg-indigo-100 text-indigo-800 px-3 py-1.5 rounded-md text-xs font-medium">
                    Filter: <span class="font-bold">{{ $vm->search }}</span>
                    <a href="{{ route('jobs-monitor.dashboard', ['period' => $vm->period]) }}"
                       class="ml-1 text-indigo-500 hover:text-indigo-800" title="Remove filter">&times;</a>
                </div>
            @endif
            <a href="{{ url()->full() }}" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-3 py-1.5 text-xs font-medium rounded-md transition-colors flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                Refresh
            </a>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Total</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ number_format($vm->statusCounts['total']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Processed</div>
            <div class="mt-1 text-3xl font-bold text-green-600">{{ number_format($vm->statusCounts['processed']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Failed</div>
            <div class="mt-1 text-3xl font-bold {{ $vm->statusCounts['failed'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($vm->statusCounts['failed']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Processing</div>
            <div class="mt-1 text-3xl font-bold {{ $vm->statusCounts['processing'] > 0 ? 'text-yellow-600' : 'text-gray-900' }}">{{ number_format($vm->statusCounts['processing']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Success Rate</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ $vm->successRate() }}</div>
        </div>
    </div>

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
                                <td class="px-5 py-3 text-red-600 truncate max-w-xs" title="{{ $job['exception'] ?? '' }}">{{ \Illuminate\Support\Str::limit($job['exception'] ?? '', 60) }}</td>
                            </tr>
                            <tr class="hidden">
                                <td colspan="6" class="px-5 py-4 bg-red-50/40">
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
                                @if($job['status'] === 'processed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">processed</span>
                                @elseif($job['status'] === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">failed</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">processing</span>
                                @endif
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
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div><span class="text-xs font-medium text-gray-500 uppercase">UUID</span><p class="text-sm font-mono text-gray-900">{{ $job['uuid'] }}</p></div>
                                    <div><span class="text-xs font-medium text-gray-500 uppercase">Full Class</span><p class="text-sm font-mono text-gray-900 break-all">{{ $job['job_class'] }}</p></div>
                                    <div><span class="text-xs font-medium text-gray-500 uppercase">Finished At</span><p class="text-sm text-gray-900">{{ $job['finished_at'] ?? '—' }}</p></div>
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
