@php
    $summaryEndpoint = route('jobs-monitor.summary');
    $summaryQuery = array_filter([
        'period' => $vm->period,
        'search' => $vm->search,
        'queue' => $vm->queue,
        'connection' => $vm->connection,
        'failure_category' => $vm->failureCategory,
    ], static fn ($v) => $v !== '' && $v !== null);
@endphp

<script>
(function () {
    const endpoint = @json($summaryEndpoint);
    const query = @json((object) $summaryQuery);
    const intervalMs = 5000;

    const numberFormat = new Intl.NumberFormat('en-US');

    function buildUrl() {
        const url = new URL(endpoint, window.location.origin);
        Object.keys(query).forEach(function (k) { url.searchParams.set(k, query[k]); });
        return url.toString();
    }

    function setCard(key, value) {
        const el = document.querySelector('[data-summary-card="' + key + '"]');
        if (el) el.textContent = value;
    }

    function setCardWithAccent(key, value, active, activeClass) {
        const el = document.querySelector('[data-summary-card="' + key + '"]');
        if (!el) return;
        el.textContent = value;
        el.classList.remove('text-gray-900', activeClass);
        el.classList.add(active ? activeClass : 'text-gray-900');
    }

    function formatSuccessRate(total, processed) {
        if (total === 0) return '—';
        return (processed / total * 100).toFixed(1) + '%';
    }

    function refresh() {
        if (document.hidden) return;

        fetch(buildUrl(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (payload) {
                const d = payload.data || {};
                setCard('total', numberFormat.format(d.total || 0));
                setCard('processed', numberFormat.format(d.processed || 0));
                setCardWithAccent('failed', numberFormat.format(d.failed || 0), (d.failed || 0) > 0, 'text-red-600');
                setCardWithAccent('processing', numberFormat.format(d.processing || 0), (d.processing || 0) > 0, 'text-yellow-600');
                setCard('success_rate', formatSuccessRate(d.total || 0, d.processed || 0));
            })
            .catch(function () { /* keep previous values on error */ });
    }

    function start() {
        setInterval(refresh, intervalMs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
