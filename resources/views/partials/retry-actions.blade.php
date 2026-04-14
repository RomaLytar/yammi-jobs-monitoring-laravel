@php
    /**
     * Inline Retry + Edit&retry action group for a failed job row.
     *
     * Props:
     *   - $job           (array) formatted job row with uuid, short_class, has_payload
     *   - $retryEnabled  (bool)  config flag: jobs-monitor.store_payload
     */
    $uuid = $job['uuid'];
    $canRetry = $retryEnabled && ! empty($job['has_payload']);
@endphp
<div class="inline-flex items-center gap-1">
    @if($canRetry)
        <form method="POST" action="{{ route('jobs-monitor.dlq.retry', ['uuid' => $uuid]) }}" class="inline-block">
            @csrf
            <button type="submit"
                    title="Retry this job now"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-primary hover:bg-primary/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <i data-lucide="refresh-cw" class="text-[14px]"></i>
                <span class="sr-only">Retry</span>
            </button>
        </form>
        <a href="{{ route('jobs-monitor.dlq.edit', ['uuid' => $uuid]) }}"
           title="Edit payload and retry"
           class="inline-flex h-7 w-7 items-center justify-center rounded-md text-brand hover:bg-brand/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <i data-lucide="pencil" class="text-[14px]"></i>
            <span class="sr-only">Edit &amp; retry</span>
        </a>
    @else
        <span class="text-[11px] text-muted-foreground italic"
              title="Set JOBS_MONITOR_STORE_PAYLOAD=true to enable retry">no payload</span>
    @endif
</div>
