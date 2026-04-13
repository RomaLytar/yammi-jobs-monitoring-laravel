@php
    $chartEndpoint = route('jobs-monitor.time-series');
    $chartPeriod = $period ?? '24h';
@endphp

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Throughput</h2>
            <p class="text-xs text-gray-500" data-time-series-subtitle>Loading…</p>
        </div>
        <div class="text-xs text-gray-500" data-time-series-bucket></div>
    </div>
    <div class="relative h-56">
        <canvas data-time-series-chart></canvas>
        <div data-time-series-empty class="hidden absolute inset-0 flex items-center justify-center text-sm text-gray-400">
            No data in the selected period.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const endpoint = @json($chartEndpoint);
    const period = @json($chartPeriod);

    function formatLabel(t, bucketSize) {
        const d = new Date(t);
        if (bucketSize === 'day') {
            return d.toISOString().slice(0, 10);
        }
        if (bucketSize === 'hour') {
            return d.toISOString().slice(0, 13).replace('T', ' ') + ':00';
        }
        return d.toISOString().slice(11, 16);
    }

    function render() {
        const canvas = document.querySelector('[data-time-series-chart]');
        const subtitle = document.querySelector('[data-time-series-subtitle]');
        const bucketEl = document.querySelector('[data-time-series-bucket]');
        const emptyEl = document.querySelector('[data-time-series-empty]');

        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('period', period);

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (payload) {
                const data = payload.data || {};
                const buckets = data.buckets || [];
                const bucketSize = data.bucket_size || 'hour';

                const labels = buckets.map(function (b) { return formatLabel(b.t, bucketSize); });
                const processed = buckets.map(function (b) { return b.processed; });
                const failed = buckets.map(function (b) { return b.failed; });

                const totalProcessed = processed.reduce(function (a, b) { return a + b; }, 0);
                const totalFailed = failed.reduce(function (a, b) { return a + b; }, 0);

                subtitle.textContent = 'Processed ' + totalProcessed + ' · Failed ' + totalFailed;
                bucketEl.textContent = bucketSize.charAt(0).toUpperCase() + bucketSize.slice(1) + ' buckets';

                if (totalProcessed === 0 && totalFailed === 0) {
                    emptyEl.classList.remove('hidden');
                }

                new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Processed',
                                data: processed,
                                borderColor: 'rgb(22, 163, 74)',
                                backgroundColor: 'rgba(22, 163, 74, 0.15)',
                                tension: 0.2,
                                fill: true,
                                pointRadius: 0,
                                borderWidth: 2,
                            },
                            {
                                label: 'Failed',
                                data: failed,
                                borderColor: 'rgb(220, 38, 38)',
                                backgroundColor: 'rgba(220, 38, 38, 0.15)',
                                tension: 0.2,
                                fill: true,
                                pointRadius: 0,
                                borderWidth: 2,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                            tooltip: { enabled: true },
                        },
                        scales: {
                            x: { ticks: { maxTicksLimit: 12, font: { size: 10 } }, grid: { display: false } },
                            y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
                        },
                    },
                });
            })
            .catch(function () {
                subtitle.textContent = 'Failed to load chart.';
            });
    }

    function boot(attempt) {
        if (typeof Chart !== 'undefined') {
            render();
            return;
        }
        if (attempt > 50) {
            return;
        }
        setTimeout(function () { boot(attempt + 1); }, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(0); });
    } else {
        boot(0);
    }
})();
</script>
