<script>
(function () {
    var endpoint = @json(route('jobs-monitor.workers.summary'));
    var intervalMs = 5000;
    var numberFormat = new Intl.NumberFormat('en-US');

    function setCard(key, value) {
        var el = document.querySelector('[data-workers-card="' + key + '"]');
        if (el) el.textContent = value;
    }

    function setCardWithAccent(key, value, active, activeClass) {
        var el = document.querySelector('[data-workers-card="' + key + '"]');
        if (!el) return;
        el.textContent = value;
        el.classList.remove('text-foreground', 'text-success', 'text-warning', 'text-destructive');
        el.classList.add(active ? activeClass : 'text-foreground');
    }

    function refresh() {
        if (document.hidden) return;

        fetch(endpoint, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (payload) {
                var d = payload.data || {};
                setCardWithAccent('alive', numberFormat.format(d.alive || 0), (d.alive || 0) > 0, 'text-success');
                setCardWithAccent('silent', numberFormat.format(d.silent || 0), (d.silent || 0) > 0, 'text-warning');
                setCardWithAccent('dead', numberFormat.format(d.dead || 0), (d.dead || 0) > 0, 'text-destructive');
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
