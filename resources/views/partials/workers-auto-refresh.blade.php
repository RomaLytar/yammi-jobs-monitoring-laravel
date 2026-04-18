<script>
(function () {
    var endpoint = @json(route('jobs-monitor.workers.summary'));
    var intervalMs = @json($vm->silentAfterSeconds) * 1000;
    var container = document.getElementById('workers-live');

    if (!container) return;

    function refresh() {
        if (document.hidden) return;

        fetch(endpoint, { headers: { 'Accept': 'text/html' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r); })
            .then(function (html) {
                container.innerHTML = html;

                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }
            })
            .catch(function () { /* keep previous content on error */ });
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
