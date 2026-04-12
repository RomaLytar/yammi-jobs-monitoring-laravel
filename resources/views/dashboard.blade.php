@extends('jobs-monitor::layouts.app')

@section('content')
    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Total Jobs</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ $vm->totalJobs }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Failures (24h)</div>
            <div class="mt-1 text-3xl font-bold {{ $vm->totalFailures > 0 ? 'text-red-600' : 'text-gray-900' }}">
                {{ $vm->totalFailures }}
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Success Rate</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">
                @if($vm->totalJobs > 0)
                    {{ number_format((($vm->totalJobs - $vm->totalFailures) / $vm->totalJobs) * 100, 1) }}%
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    {{-- Recent failures --}}
    @if(count($vm->recentFailures) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-red-200 mb-6">
            <div class="px-5 py-4 border-b border-red-200">
                <h2 class="text-base font-semibold text-red-700">Recent Failures</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-red-50 text-left text-xs font-medium text-red-700 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3">Job</th>
                            <th class="px-5 py-3">Queue</th>
                            <th class="px-5 py-3">Attempt</th>
                            <th class="px-5 py-3">Failed At</th>
                            <th class="px-5 py-3">Duration</th>
                            <th class="px-5 py-3">Exception</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100">
                        @foreach($vm->recentFailures as $job)
                            <tr class="hover:bg-red-50/50">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ $job['short_class'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['queue'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['attempt'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['finished_at'] ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $job['duration_formatted'] }}</td>
                                <td class="px-5 py-3 text-red-600 truncate max-w-xs" title="{{ $job['exception'] ?? '' }}">
                                    {{ \Illuminate\Support\Str::limit($job['exception'] ?? '—', 80) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Recent jobs --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Recent Jobs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Job</th>
                        <th class="px-5 py-3">Connection</th>
                        <th class="px-5 py-3">Queue</th>
                        <th class="px-5 py-3">Attempt</th>
                        <th class="px-5 py-3">Started At</th>
                        <th class="px-5 py-3">Duration</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($vm->recentJobs as $job)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                @if($job['status'] === 'processed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        processed
                                    </span>
                                @elseif($job['status'] === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        failed
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        processing
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $job['short_class'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['connection'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['queue'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['attempt'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['started_at'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $job['duration_formatted'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                                No jobs recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
