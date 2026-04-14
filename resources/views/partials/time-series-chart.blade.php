@php
    $chartEndpoint = route('jobs-monitor.time-series');
    $chartPeriod = $period ?? '24h';
@endphp

<div class="relative overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xs mb-6">
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/50 to-transparent"></div>
    <div class="flex items-start justify-between gap-4 px-5 pt-5 pb-3">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="line-chart" class="text-[17px]"></i>
            </div>
            <div>
                <h2 class="text-sm font-semibold tracking-tight">Throughput</h2>
                <p class="text-xs text-muted-foreground mt-0.5" data-time-series-subtitle>Loading…</p>
            </div>
        </div>
        <div class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground" data-time-series-bucket></div>
    </div>
    <div class="px-5 pb-5">
        <div class="relative h-56">
            <canvas data-time-series-chart></canvas>
            <div data-time-series-empty class="hidden absolute inset-0 flex flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                <i data-lucide="inbox" class="text-2xl"></i>
                No data in the selected period.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const endpoint = @json($chartEndpoint);
    const period = @json($chartPeriod);
    const intervalMs = 5000;

    let chart = null;

    function themeColors() {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            success: isDark ? 'rgb(34, 197, 94)' : 'rgb(22, 163, 74)',
            successFill: isDark ? 'rgba(34, 197, 94, 0.18)' : 'rgba(22, 163, 74, 0.12)',
            destructive: isDark ? 'rgb(248, 113, 113)' : 'rgb(220, 38, 38)',
            destructiveFill: isDark ? 'rgba(248, 113, 113, 0.2)' : 'rgba(220, 38, 38, 0.12)',
            muted: isDark ? 'rgba(255,255,255,0.55)' : 'rgba(71, 85, 105, 0.7)',
            grid: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(15, 23, 42, 0.06)',
        };
    }

    function formatLabel(t, bucketSize) {
        const d = new Date(t);
        if (bucketSize === 'day') return d.toISOString().slice(0, 10);
        if (bucketSize === 'hour') return d.toISOString().slice(0, 13).replace('T', ' ') + ':00';
        return d.toISOString().slice(11, 16);
    }

    function fetchData() {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('period', period);
        return fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); });
    }

    function applyData(payload, canvas, subtitle, bucketEl, emptyEl) {
        const data = payload.data || {};
        const buckets = data.buckets || [];
        const bucketSize = data.bucket_size || 'hour';

        const labels = buckets.map(function (b) { return formatLabel(b.t, bucketSize); });
        const processed = buckets.map(function (b) { return b.processed; });
        const failed = buckets.map(function (b) { return b.failed; });

        const totalProcessed = processed.reduce(function (a, b) { return a + b; }, 0);
        const totalFailed = failed.reduce(function (a, b) { return a + b; }, 0);

        subtitle.textContent = 'Processed ' + totalProcessed.toLocaleString() + ' · Failed ' + totalFailed.toLocaleString();
        bucketEl.textContent = bucketSize + ' buckets';

        if (totalProcessed === 0 && totalFailed === 0) {
            emptyEl.classList.remove('hidden');
        } else {
            emptyEl.classList.add('hidden');
        }

        const colors = themeColors();

        if (chart === null) {
            chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Processed',
                            data: processed,
                            borderColor: colors.success,
                            backgroundColor: colors.successFill,
                            tension: 0.35,
                            fill: true,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            borderWidth: 2,
                        },
                        {
                            label: 'Failed',
                            data: failed,
                            borderColor: colors.destructive,
                            backgroundColor: colors.destructiveFill,
                            tension: 0.35,
                            fill: true,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            borderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    animation: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, boxHeight: 10, font: { size: 11, family: 'Inter var, Inter, sans-serif' }, color: colors.muted, usePointStyle: true, pointStyle: 'circle' },
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            borderColor: 'rgba(255,255,255,0.08)',
                            borderWidth: 1,
                            titleFont: { size: 11, weight: '600' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            cornerRadius: 8,
                            boxPadding: 4,
                        },
                    },
                    scales: {
                        x: { ticks: { maxTicksLimit: 10, font: { size: 10 }, color: colors.muted }, grid: { display: false }, border: { color: colors.grid } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: colors.muted }, grid: { color: colors.grid }, border: { display: false } },
                    },
                },
            });
        } else {
            chart.data.labels = labels;
            chart.data.datasets[0].data = processed;
            chart.data.datasets[0].borderColor = colors.success;
            chart.data.datasets[0].backgroundColor = colors.successFill;
            chart.data.datasets[1].data = failed;
            chart.data.datasets[1].borderColor = colors.destructive;
            chart.data.datasets[1].backgroundColor = colors.destructiveFill;
            if (chart.options.scales.x.ticks) chart.options.scales.x.ticks.color = colors.muted;
            if (chart.options.scales.y.ticks) chart.options.scales.y.ticks.color = colors.muted;
            if (chart.options.scales.y.grid)  chart.options.scales.y.grid.color = colors.grid;
            if (chart.options.plugins.legend && chart.options.plugins.legend.labels) chart.options.plugins.legend.labels.color = colors.muted;
            chart.update('none');
        }
    }

    function render() {
        const canvas = document.querySelector('[data-time-series-chart]');
        const subtitle = document.querySelector('[data-time-series-subtitle]');
        const bucketEl = document.querySelector('[data-time-series-bucket]');
        const emptyEl = document.querySelector('[data-time-series-empty]');

        if (!canvas || typeof Chart === 'undefined') return;

        fetchData()
            .then(function (payload) { applyData(payload, canvas, subtitle, bucketEl, emptyEl); })
            .catch(function () { subtitle.textContent = 'Failed to load chart.'; });
    }

    function refresh() {
        if (document.hidden || chart === null) return;
        render();
    }

    function boot(attempt) {
        if (typeof Chart !== 'undefined') {
            render();
            setInterval(refresh, intervalMs);
            return;
        }
        if (attempt > 50) return;
        setTimeout(function () { boot(attempt + 1); }, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(0); });
    } else {
        boot(0);
    }

    // Re-render with new palette when dark mode flips.
    const observer = new MutationObserver(function () {
        if (chart) {
            chart.destroy();
            chart = null;
            render();
        }
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
})();
</script>
