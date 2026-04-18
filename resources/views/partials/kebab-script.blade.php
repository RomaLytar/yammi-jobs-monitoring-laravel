<script>
    if (!window.__jmToggleKebab) {
        window.__jmToggleKebab = function (button) {
            var dropdown = button.nextElementSibling;
            document.querySelectorAll('[data-jm-kebab-dropdown]').forEach(function (d) {
                if (d !== dropdown) d.classList.add('hidden');
            });
            dropdown.classList.toggle('hidden');
            if (window.__jmRefreshIcons) window.__jmRefreshIcons();
        };
        document.addEventListener('click', function (e) {
            document.querySelectorAll('[data-jm-kebab]').forEach(function (m) {
                if (!m.contains(e.target)) {
                    var dd = m.querySelector('[data-jm-kebab-dropdown]');
                    if (dd) dd.classList.add('hidden');
                }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('[data-jm-kebab-dropdown]').forEach(function (d) {
                    d.classList.add('hidden');
                });
            }
        });
    }
</script>
