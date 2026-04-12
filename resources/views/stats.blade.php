@extends('jobs-monitor::layouts.app')

@section('content')
    {{-- Period pills --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-1">
            @foreach($vm->periods as $key => $label)
                <a href="{{ route('jobs-monitor.stats', ['period' => $key]) }}"
                   class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                          {{ $vm->period === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $key }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Total Jobs</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ number_format($vm->totals['total']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Failure Rate</div>
            <div class="mt-1 text-3xl font-bold {{ $vm->totals['failed'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $vm->overallFailureRate() }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ number_format($vm->totals['failed']) }} failed</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Retry Rate</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ $vm->overallRetryRate() }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ number_format($vm->totals['retries']) }} retried</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="text-sm font-medium text-gray-500">Job Classes</div>
            <div class="mt-1 text-3xl font-bold text-gray-900">{{ number_format(count($vm->byClass)) }}</div>
        </div>
    </div>

    @if(count($vm->byClass) === 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 text-center text-gray-500">
            No jobs recorded for the selected period.
        </div>
    @else
        {{-- Top tables --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Most failing --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Most failing jobs</h2>
                </div>
                @if(count($vm->mostFailing) === 0)
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">No failures in this period.</div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Job</th>
                                <th class="px-5 py-3">Failed</th>
                                <th class="px-5 py-3">Total</th>
                                <th class="px-5 py-3">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($vm->mostFailing as $row)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-900" title="{{ $row['job_class'] }}">{{ $row['short_class'] }}</td>
                                    <td class="px-5 py-3 text-red-600 font-semibold">{{ number_format($row['failed']) }}</td>
                                    <td class="px-5 py-3 text-gray-600">{{ number_format($row['total']) }}</td>
                                    <td class="px-5 py-3 text-gray-700">{{ number_format($row['failure_rate'] * 100, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Slowest --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Slowest jobs (by avg duration)</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Job</th>
                            <th class="px-5 py-3">Avg</th>
                            <th class="px-5 py-3">Max</th>
                            <th class="px-5 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($vm->slowest as $row)
                            <tr>
                                <td class="px-5 py-3 font-medium text-gray-900" title="{{ $row['job_class'] }}">{{ $row['short_class'] }}</td>
                                <td class="px-5 py-3 text-gray-700 font-semibold">{{ $row['avg_duration_formatted'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $row['max_duration_formatted'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Full breakdown --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-base font-semibold text-gray-900">All job classes <span class="text-sm font-normal text-gray-500">({{ count($vm->byClass) }} classes)</span></h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Job class</th>
                            <th class="px-5 py-3">Total</th>
                            <th class="px-5 py-3">Processed</th>
                            <th class="px-5 py-3">Failed</th>
                            <th class="px-5 py-3">Failure rate</th>
                            <th class="px-5 py-3">Avg duration</th>
                            <th class="px-5 py-3">Max duration</th>
                            <th class="px-5 py-3">Retries</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($vm->byClass as $row)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3 font-medium text-gray-900" title="{{ $row['job_class'] }}">{{ $row['short_class'] }}</td>
                                <td class="px-5 py-3 text-gray-900 font-semibold">{{ number_format($row['total']) }}</td>
                                <td class="px-5 py-3 text-green-700">{{ number_format($row['processed']) }}</td>
                                <td class="px-5 py-3 {{ $row['failed'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ number_format($row['failed']) }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ number_format($row['failure_rate'] * 100, 1) }}%</td>
                                <td class="px-5 py-3 text-gray-700">{{ $row['avg_duration_formatted'] }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $row['max_duration_formatted'] }}</td>
                                <td class="px-5 py-3 {{ $row['retry_count'] > 0 ? 'text-yellow-700' : 'text-gray-400' }}">{{ number_format($row['retry_count']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
