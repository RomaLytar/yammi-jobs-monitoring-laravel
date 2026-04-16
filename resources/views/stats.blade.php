@extends('jobs-monitor::layouts.app')

@section('content')
    {{-- Page header --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Statistics</h1>
            <p class="text-sm text-muted-foreground mt-1">Aggregated metrics grouped by job class for the selected period.</p>
        </div>
    </div>

    {{-- Period pills --}}
    <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs p-3 mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider px-1">Period</span>
            <div class="inline-flex rounded-lg bg-muted p-1 gap-0.5">
                @foreach($vm->periods as $key => $label)
                    <a href="{{ route('jobs-monitor.stats', ['period' => $key]) }}"
                       class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-md transition-all
                              {{ $vm->period === $key
                                  ? 'bg-card text-foreground shadow-xs ring-1 ring-border'
                                  : 'text-muted-foreground hover:text-foreground' }}">
                        {{ $key }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    @php
        $cards = [
            [
                'label' => 'Total Jobs',
                'value' => number_format($vm->totals['total']),
                'sub'   => null,
                'icon'  => 'layers',
                'iconBg'=> 'bg-muted text-muted-foreground',
                'accent'=> 'text-foreground',
            ],
            [
                'label' => 'Failure Rate',
                'value' => $vm->overallFailureRate(),
                'sub'   => number_format($vm->totals['failed']) . ' failed',
                'icon'  => 'alert-octagon',
                'iconBg'=> 'bg-destructive/10 text-destructive',
                'accent'=> $vm->totals['failed'] > 0 ? 'text-destructive' : 'text-foreground',
            ],
            [
                'label' => 'Retry Rate',
                'value' => $vm->overallRetryRate(),
                'sub'   => number_format($vm->totals['retries']) . ' retried',
                'icon'  => 'refresh-cw',
                'iconBg'=> 'bg-warning/10 text-warning',
                'accent'=> 'text-foreground',
            ],
            [
                'label' => 'Job Classes',
                'value' => number_format(count($vm->byClass)),
                'sub'   => 'unique classes',
                'icon'  => 'boxes',
                'iconBg'=> 'bg-brand/10 text-brand',
                'accent'=> 'text-foreground',
            ],
        ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @foreach($cards as $c)
            <div class="group relative overflow-hidden rounded-xl border border-border bg-card text-card-foreground p-4 shadow-xs hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground">{{ $c['label'] }}</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-md {{ $c['iconBg'] }}">
                        <i data-lucide="{{ $c['icon'] }}" class="text-[14px]"></i>
                    </span>
                </div>
                <div class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $c['accent'] }}">{{ $c['value'] }}</div>
                @if($c['sub'])
                    <div class="mt-0.5 text-xs text-muted-foreground">{{ $c['sub'] }}</div>
                @endif
            </div>
        @endforeach
    </div>

    @if(count($vm->byClass) === 0)
        <div class="rounded-xl border border-border bg-card text-card-foreground p-12 text-center">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                <i data-lucide="inbox" class="text-xl"></i>
            </div>
            <p class="text-sm font-medium">No jobs recorded</p>
            <p class="text-xs text-muted-foreground mt-1">No jobs were recorded in the selected period.</p>
        </div>
    @else
        {{-- Top tables --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            {{-- Most failing --}}
            <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20">
                        <i data-lucide="flame" class="text-[16px]"></i>
                    </span>
                    <h2 class="text-sm font-semibold">Most failing jobs</h2>
                </div>
                @if(count($vm->mostFailing) === 0)
                    <div class="px-5 py-10 text-center text-muted-foreground text-sm">
                        <i data-lucide="shield-check" class="text-xl mb-2 inline-block text-success"></i>
                        <p>No failures in this period.</p>
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                                <th class="text-left font-medium px-5 py-2.5">Job</th>
                                <th class="text-left font-medium px-5 py-2.5">Failed</th>
                                <th class="text-left font-medium px-5 py-2.5">Total</th>
                                <th class="text-left font-medium px-5 py-2.5">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($vm->mostFailing as $row)
                                <tr class="{{ $loop->even ? 'bg-muted/40' : 'bg-card' }} hover:bg-muted/60 transition-colors">
                                    <td class="px-5 py-3 font-medium" title="{{ $row['job_class'] }}">{{ $row['short_class'] }}</td>
                                    <td class="px-5 py-3 text-destructive font-semibold tabular-nums">{{ number_format($row['failed']) }}</td>
                                    <td class="px-5 py-3 text-muted-foreground tabular-nums">{{ number_format($row['total']) }}</td>
                                    <td class="px-5 py-3 tabular-nums">
                                        @php $pct = $row['failure_rate'] * 100; @endphp
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 h-1.5 rounded-full bg-muted overflow-hidden">
                                                <div class="h-full bg-destructive rounded-full" style="width: {{ min(100, $pct) }}%"></div>
                                            </div>
                                            <span class="text-xs font-medium">{{ number_format($pct, 1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Slowest --}}
            <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/10 text-warning ring-1 ring-inset ring-warning/20">
                        <i data-lucide="timer" class="text-[16px]"></i>
                    </span>
                    <h2 class="text-sm font-semibold">Slowest jobs <span class="text-xs font-normal text-muted-foreground ml-1">by avg duration</span></h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                            <th class="text-left font-medium px-5 py-2.5">Job</th>
                            <th class="text-left font-medium px-5 py-2.5">Avg</th>
                            <th class="text-left font-medium px-5 py-2.5">Max</th>
                            <th class="text-left font-medium px-5 py-2.5">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($vm->slowest as $row)
                            <tr class="{{ $loop->even ? 'bg-muted/40' : 'bg-card' }} hover:bg-muted/60 transition-colors">
                                <td class="px-5 py-3 font-medium" title="{{ $row['job_class'] }}">{{ $row['short_class'] }}</td>
                                <td class="px-5 py-3 font-semibold tabular-nums">{{ $row['avg_duration_formatted'] }}</td>
                                <td class="px-5 py-3 text-muted-foreground tabular-nums">{{ $row['max_duration_formatted'] }}</td>
                                <td class="px-5 py-3 text-muted-foreground tabular-nums">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Full breakdown --}}
        <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                    <i data-lucide="table" class="text-[16px]"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold">All job classes</h2>
                    <p class="text-xs text-muted-foreground">{{ count($vm->byClass) }} unique classes</p>
                </div>
            </div>
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col>
                    <col class="w-[80px]">
                    <col class="hidden md:table-column w-[100px]">
                    <col class="w-[80px]">
                    <col class="w-[140px]">
                    <col class="hidden lg:table-column w-[110px]">
                    <col class="hidden xl:table-column w-[110px]">
                    <col class="hidden md:table-column w-[80px]">
                </colgroup>
                <thead>
                    <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                        <th class="text-left font-medium px-5 py-2.5">Job class</th>
                        <th class="text-left font-medium px-5 py-2.5">Total</th>
                        <th class="hidden md:table-cell text-left font-medium px-5 py-2.5">Processed</th>
                        <th class="text-left font-medium px-5 py-2.5">Failed</th>
                        <th class="text-left font-medium px-5 py-2.5">Failure rate</th>
                        <th class="hidden lg:table-cell text-left font-medium px-5 py-2.5">Avg duration</th>
                        <th class="hidden xl:table-cell text-left font-medium px-5 py-2.5">Max duration</th>
                        <th class="hidden md:table-cell text-left font-medium px-5 py-2.5">Retries</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($vm->byClass as $row)
                        <tr class="{{ $loop->even ? 'bg-muted/40' : 'bg-card' }} hover:bg-muted/60 transition-colors">
                            <td class="px-5 py-3 font-medium truncate" title="{{ $row['job_class'] }}">{{ $row['short_class'] }}</td>
                            <td class="px-5 py-3 font-semibold tabular-nums">{{ number_format($row['total']) }}</td>
                            <td class="hidden md:table-cell px-5 py-3 text-success tabular-nums">{{ number_format($row['processed']) }}</td>
                            <td class="px-5 py-3 tabular-nums {{ $row['failed'] > 0 ? 'text-destructive font-semibold' : 'text-muted-foreground' }}">{{ number_format($row['failed']) }}</td>
                            <td class="px-5 py-3 tabular-nums">
                                @php $pct = $row['failure_rate'] * 100; @endphp
                                <div class="flex items-center gap-2">
                                    <div class="w-14 h-1.5 rounded-full bg-muted overflow-hidden">
                                        <div class="h-full rounded-full {{ $pct > 10 ? 'bg-destructive' : ($pct > 0 ? 'bg-warning' : 'bg-success') }}" style="width: {{ min(100, max(2, $pct)) }}%"></div>
                                    </div>
                                    <span class="text-xs">{{ number_format($pct, 1) }}%</span>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell px-5 py-3 tabular-nums">{{ $row['avg_duration_formatted'] }}</td>
                            <td class="hidden xl:table-cell px-5 py-3 text-muted-foreground tabular-nums">{{ $row['max_duration_formatted'] }}</td>
                            <td class="hidden md:table-cell px-5 py-3 tabular-nums {{ $row['retry_count'] > 0 ? 'text-warning font-medium' : 'text-muted-foreground' }}">{{ number_format($row['retry_count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
