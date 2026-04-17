@extends('jobs-monitor::layouts.app')

@section('content')
<div class="flex items-center justify-center min-h-[calc(100vh-6rem)]">
    <div class="w-full max-w-lg rounded-2xl border border-border bg-card text-card-foreground shadow-2xl">

        {{-- Icon + title --}}
        <div class="flex flex-col items-center gap-4 pt-8 px-8 pb-5 text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-destructive/10 text-destructive ring-2 ring-inset ring-destructive/20">
                <i data-lucide="database-zap" class="text-[32px]"></i>
            </span>
            <div>
                <h1 class="text-xl font-bold tracking-tight">Monitor database unreachable</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Connection
                    <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-xs">{{ $jmMonitorConn }}</code>
                    is not reachable. Monitoring is paused until the database is available.
                </p>
            </div>
        </div>

        <div class="space-y-4 px-8 pb-8">

            @if(session('jobs_monitor_error'))
                <div class="flex items-start gap-2 rounded-lg border border-destructive/25 bg-destructive/10 text-destructive px-4 py-3 text-sm">
                    <i data-lucide="alert-circle" class="text-[14px] mt-0.5 shrink-0"></i>
                    <span>{{ session('jobs_monitor_error') }}</span>
                </div>
            @endif

            {{-- Option 1: auto setup --}}
            <div class="rounded-xl border border-border bg-muted/30 p-5 space-y-3">
                <p class="text-sm font-semibold flex items-center gap-2">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand/15 text-brand text-[11px] font-bold ring-1 ring-brand/20">1</span>
                    Let us create it automatically
                </p>
                <p class="text-xs text-muted-foreground">
                    Creates the database (if possible), runs migrations, and copies your existing monitoring data.
                </p>
                <form method="POST" action="{{ route('jobs-monitor.settings.database.setup') }}">
                    @csrf
                    @include('jobs-monitor::partials.button', [
                        'variant' => 'brand',
                        'icon'    => 'database',
                        'label'   => 'Setup Monitor DB & Transfer Data',
                    ])
                </form>
            </div>

            {{-- Option 2: manual --}}
            <div class="rounded-xl border border-border bg-muted/30 p-5 space-y-3">
                <p class="text-sm font-semibold flex items-center gap-2">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-muted text-muted-foreground text-[11px] font-bold ring-1 ring-border">2</span>
                    Create the database manually, then run
                </p>
                <div class="rounded-lg bg-muted px-4 py-2.5 font-mono text-xs select-all text-foreground">
                    php artisan jobs-monitor:transfer-data
                </div>
            </div>

            {{-- Option 3: remove config --}}
            <div class="rounded-xl border border-border bg-muted/30 p-5 space-y-3">
                <p class="text-sm font-semibold flex items-center gap-2">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-muted text-muted-foreground text-[11px] font-bold ring-1 ring-border">3</span>
                    Revert to the default connection
                </p>
                <p class="text-xs text-muted-foreground">Remove or clear the env variable to use your app's default database:</p>
                <div class="rounded-lg bg-muted px-4 py-2.5 font-mono text-xs text-muted-foreground">
                    JOBS_MONITOR_DB_CONNECTION=
                </div>
            </div>

            <div class="flex justify-center pt-1">
                <a href="{{ route('jobs-monitor.settings.database') }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                    <i data-lucide="settings" class="text-[14px]"></i>
                    View Database Settings
                </a>
            </div>

        </div>
    </div>
</div>
@endsection
