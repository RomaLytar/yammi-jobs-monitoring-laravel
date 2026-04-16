{{--
    Page-level confirmation modal, populated via JS.
    Trigger with:
        window.__jmOpenConfirm({
            action: '/url', method: 'POST',
            title: 'Delete entry?',
            body: 'This cannot be undone.',
            submitLabel: 'Delete',
            icon: 'trash-2',
            variant: 'danger', // or 'primary'
        })
    Kebab items with `'confirm' => true` render as triggers automatically.
--}}
<div id="jm-confirm-modal"
     class="hidden fixed inset-0 z-50 overflow-y-auto"
     role="dialog" aria-modal="true" aria-labelledby="jm-confirm-title">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity" data-jm-confirm-close></div>
        <div class="relative w-full max-w-md transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border animate-slide-down">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/20" data-jm-confirm-iconwrap>
                        <i data-lucide="alert-triangle" class="text-[18px]"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 id="jm-confirm-title" class="text-base font-semibold" data-jm-confirm-title>Confirm</h3>
                        <p class="mt-2 text-sm text-muted-foreground" data-jm-confirm-body>Are you sure?</p>
                    </div>
                </div>
            </div>
            <div class="bg-muted/40 px-6 py-3 flex justify-end gap-2 border-t border-border">
                @include('jobs-monitor::partials.button', [
                    'variant' => 'secondary',
                    'label' => 'Cancel',
                    'attrs' => 'data-jm-confirm-close',
                ])
                <form method="POST" action="" data-jm-confirm-form class="m-0">
                    @csrf
                    @include('jobs-monitor::partials.button', [
                        'variant' => 'danger',
                        'label' => 'Confirm',
                        'icon' => 'check',
                        'as' => 'submit',
                        'attrs' => 'data-jm-confirm-submit',
                    ])
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.__jmOpenConfirm) return;

        var modal = document.getElementById('jm-confirm-modal');
        if (!modal) return;

        var iconWrap = modal.querySelector('[data-jm-confirm-iconwrap]');
        var titleEl  = modal.querySelector('[data-jm-confirm-title]');
        var bodyEl   = modal.querySelector('[data-jm-confirm-body]');
        var form     = modal.querySelector('[data-jm-confirm-form]');
        var submit   = modal.querySelector('[data-jm-confirm-submit]');

        var palettes = {
            danger:  { ring: 'bg-destructive/10 text-destructive ring-destructive/20', btn: 'bg-destructive text-destructive-foreground hover:bg-destructive/90' },
            primary: { ring: 'bg-primary/10 text-primary ring-primary/20',             btn: 'bg-primary text-primary-foreground hover:bg-primary/90' },
            warning: { ring: 'bg-warning/10 text-warning ring-warning/20',             btn: 'bg-warning text-warning-foreground hover:bg-warning/90' },
        };

        window.__jmOpenConfirm = function (opts) {
            var p = palettes[opts.variant] || palettes.danger;
            var icon = opts.icon || (opts.variant === 'primary' ? 'refresh-cw' : 'trash-2');

            iconWrap.className = 'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full ring-1 ring-inset ' + p.ring;
            iconWrap.replaceChildren();
            var iEl = document.createElement('i');
            iEl.setAttribute('data-lucide', icon);
            iEl.className = 'text-[18px]';
            iconWrap.appendChild(iEl);

            titleEl.textContent = opts.title || 'Confirm';
            bodyEl.replaceChildren();
            if (opts.bodyHtml) {
                bodyEl.innerHTML = opts.bodyHtml;
            } else {
                bodyEl.textContent = opts.body || 'Are you sure?';
            }

            form.action = opts.action || '#';
            form.method = (opts.method || 'POST').toUpperCase() === 'GET' ? 'GET' : 'POST';

            submit.className = 'inline-flex items-center gap-1.5 rounded-md font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring h-9 px-4 text-sm shadow-xs ' + p.btn;
            submit.replaceChildren();
            var sIcon = document.createElement('i');
            sIcon.setAttribute('data-lucide', icon);
            sIcon.className = 'text-[14px]';
            submit.appendChild(sIcon);
            submit.append(' ' + (opts.submitLabel || 'Confirm'));

            modal.classList.remove('hidden');
            if (window.__jmRefreshIcons) window.__jmRefreshIcons();
        };

        window.__jmCloseConfirm = function () { modal.classList.add('hidden'); };

        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-jm-confirm-close]')) window.__jmCloseConfirm();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) window.__jmCloseConfirm();
        });

        // Wire kebab items flagged as confirmable.
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-jm-confirm-trigger]');
            if (!trigger) return;
            e.preventDefault();
            window.__jmOpenConfirm({
                action:      trigger.getAttribute('data-jm-action') || '#',
                method:      trigger.getAttribute('data-jm-method') || 'POST',
                title:       trigger.getAttribute('data-jm-title'),
                body:        trigger.getAttribute('data-jm-body'),
                submitLabel: trigger.getAttribute('data-jm-submit'),
                icon:        trigger.getAttribute('data-jm-icon'),
                variant:     trigger.getAttribute('data-jm-variant') || 'danger',
            });
        });
    })();
</script>
