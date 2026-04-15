@php
    /** @var \Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun $run */
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
    @foreach ([
        ['Mutex', $run->mutex, true],
        ['Command', $run->command ?? '—', true],
        ['Cron', $run->expression, true],
        ['Timezone', $run->timezone ?? '—', false],
        ['Started', $run->startedAt->format('Y-m-d H:i:s'), false],
        ['Finished', $run->finishedAt() ? $run->finishedAt()->format('Y-m-d H:i:s') : '—', false],
        ['Exit code', $run->exitCode() === null ? '—' : (string) $run->exitCode(), false],
        ['Host', $run->host ?? '—', false],
    ] as [$label, $val, $mono])
        <div>
            <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">{{ $label }}</span>
            <p class="text-sm {{ $mono ? 'font-mono break-all' : '' }}">{{ $val }}</p>
        </div>
    @endforeach
</div>

@if ($run->output())
    <div class="mb-3">
        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Output</span>
        <pre class="mt-1 bg-card border border-border rounded-lg p-3 text-xs overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ $run->output() }}</pre>
    </div>
@endif

@if ($run->exception())
    <div>
        <span class="text-[10px] font-medium text-destructive uppercase tracking-wider">Exception</span>
        <pre class="mt-1 bg-destructive/10 border border-destructive/20 rounded-lg p-3 text-xs text-destructive overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ $run->exception() }}</pre>
    </div>
@endif
