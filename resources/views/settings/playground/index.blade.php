@extends('jobs-monitor::layouts.app')

@php
    /** @var array<string, array<int, \Yammi\JobsMonitor\Application\Playground\PlaygroundMethod>> $grouped */
    /** @var array<string, array{tone: string, summary: string}> $facadeInfo */
    $firstFacade = array_key_first($grouped);
    $firstMethod = $grouped[$firstFacade][0] ?? null;
    $toneClasses = [
        'info' => 'bg-brand/10 text-foreground ring-brand/25',
        'warning' => 'bg-warning/10 text-foreground ring-warning/30',
        'danger' => 'bg-destructive/10 text-foreground ring-destructive/30',
    ];
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="terminal" class="text-[18px]"></i>
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Facade Playground</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Call any public YammiJobs facade method from here. Pick a method, fill the arguments, run it.
                    The JSON result renders below. Destructive methods require confirmation.
                </p>
            </div>
        </div>
        <a href="{{ route('jobs-monitor.settings') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
            <i data-lucide="arrow-left" class="text-[14px]"></i>
            Back to settings
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
        {{-- Method catalog sidebar --}}
        <aside class="rounded-xl border border-border bg-card p-4 space-y-4">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Search</label>
                <input type="text" id="jm-pg-search"
                       placeholder="Filter methods..."
                       class="mt-1 w-full h-9 rounded-md border border-input bg-card text-foreground px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring">
            </div>
            <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1" id="jm-pg-list">
                @foreach($grouped as $facade => $methods)
                    @php $info = $facadeInfo[$facade] ?? ['tone' => 'info', 'summary' => '']; @endphp
                    <div data-facade="{{ $facade }}">
                        <div class="flex items-center justify-between pb-1">
                            <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $facade }}</div>
                            <span class="text-[10px] font-mono text-muted-foreground">{{ count($methods) }} methods</span>
                        </div>
                        @if($info['summary'])
                            <div class="mb-2 rounded-md px-2.5 py-1.5 text-[11px] leading-snug ring-1 ring-inset {{ $toneClasses[$info['tone']] ?? $toneClasses['info'] }}">
                                {{ $info['summary'] }}
                            </div>
                        @endif
                        <ul class="space-y-0.5">
                            @foreach($methods as $m)
                                <li>
                                    <button type="button"
                                            class="jm-pg-method w-full text-left px-2.5 py-1.5 rounded-md text-sm hover:bg-accent transition-colors flex items-center gap-2"
                                            data-key="{{ $m->key }}"
                                            data-facade="{{ $m->facade }}"
                                            data-method="{{ $m->method }}"
                                            data-destructive="{{ $m->destructive ? '1' : '0' }}"
                                            data-description="{{ $m->description }}"
                                            data-returns="{{ $m->returns }}"
                                            data-args="{{ json_encode(array_map(fn($a) => [
                                                'name' => $a->name,
                                                'type' => $a->type->value,
                                                'required' => $a->required,
                                                'default' => $a->default,
                                                'help' => $a->help,
                                            ], $m->arguments), JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                                        @if($m->destructive)
                                            <i data-lucide="alert-triangle" class="text-[12px] text-destructive flex-shrink-0"></i>
                                        @else
                                            <i data-lucide="circle-dot" class="text-[12px] text-muted-foreground flex-shrink-0"></i>
                                        @endif
                                        <span class="font-mono text-xs">{{ $m->method }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </aside>

        {{-- Form + output --}}
        <main class="space-y-5">
            <section class="rounded-xl border border-border bg-card p-5 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold" id="jm-pg-title">Select a method</h2>
                        <p class="text-sm text-muted-foreground mt-1" id="jm-pg-description">Pick a method from the list on the left to see its arguments and run it.</p>
                    </div>
                    <div class="text-xs px-2 py-1 rounded bg-muted font-mono text-muted-foreground" id="jm-pg-returns"></div>
                </div>

                <form id="jm-pg-form" class="space-y-3" onsubmit="return false">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" id="jm-pg-method-key" name="method" value="">
                    <div id="jm-pg-args" class="space-y-3"></div>
                    <div class="flex items-center gap-3 pt-1" id="jm-pg-actions" style="display: none">
                        @include('jobs-monitor::partials.button', [
                            'variant' => 'brand',
                            'type' => 'button',
                            'icon' => 'play',
                            'label' => 'Run',
                            'attrs' => 'id="jm-pg-run"',
                        ])
                        <span class="text-xs text-muted-foreground" id="jm-pg-destructive-warn" style="display: none">
                            <i data-lucide="alert-triangle" class="text-[12px] inline"></i>
                            Destructive — confirmation required.
                        </span>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-border bg-card p-5 space-y-3" id="jm-pg-result-wrap" style="display: none">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Result</h3>
                    <div class="flex items-center gap-2">
                        <span id="jm-pg-status" class="text-xs px-2 py-0.5 rounded font-mono"></span>
                        <button type="button" id="jm-pg-copy"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                            <i data-lucide="copy" class="text-[12px]"></i> Copy
                        </button>
                    </div>
                </div>
                <pre class="text-xs bg-muted/40 rounded-lg p-4 overflow-x-auto max-h-[50vh]"><code id="jm-pg-result"></code></pre>
            </section>
        </main>
    </div>
</div>

{{-- Confirm modal for destructive calls --}}
<div id="jm-pg-confirm" class="fixed inset-0 z-50 hidden items-center justify-center bg-background/80 backdrop-blur">
    <div class="bg-card border border-border rounded-xl shadow-2xl p-6 max-w-md w-full mx-4 space-y-4">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/25">
                <i data-lucide="alert-triangle" class="text-[18px]"></i>
            </span>
            <div>
                <h3 class="text-base font-semibold">Run destructive method?</h3>
                <p class="text-sm text-muted-foreground mt-1" id="jm-pg-confirm-body">This will mutate data. Continue?</p>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            @include('jobs-monitor::partials.button', ['variant' => 'secondary', 'type' => 'button', 'label' => 'Cancel', 'attrs' => 'id="jm-pg-confirm-cancel"'])
            @include('jobs-monitor::partials.button', ['variant' => 'danger', 'type' => 'button', 'label' => 'Run anyway', 'attrs' => 'id="jm-pg-confirm-ok"'])
        </div>
    </div>
</div>

<script>
(function () {
    const EXECUTE_URL = @json(route('jobs-monitor.settings.playground.execute'));
    const CSRF = @json(csrf_token());

    const ui = {
        search: document.getElementById('jm-pg-search'),
        list: document.getElementById('jm-pg-list'),
        title: document.getElementById('jm-pg-title'),
        description: document.getElementById('jm-pg-description'),
        returns: document.getElementById('jm-pg-returns'),
        form: document.getElementById('jm-pg-form'),
        methodKey: document.getElementById('jm-pg-method-key'),
        args: document.getElementById('jm-pg-args'),
        actions: document.getElementById('jm-pg-actions'),
        run: document.getElementById('jm-pg-run'),
        destructiveWarn: document.getElementById('jm-pg-destructive-warn'),
        resultWrap: document.getElementById('jm-pg-result-wrap'),
        result: document.getElementById('jm-pg-result'),
        status: document.getElementById('jm-pg-status'),
        copy: document.getElementById('jm-pg-copy'),
        confirm: document.getElementById('jm-pg-confirm'),
        confirmBody: document.getElementById('jm-pg-confirm-body'),
        confirmOk: document.getElementById('jm-pg-confirm-ok'),
        confirmCancel: document.getElementById('jm-pg-confirm-cancel'),
    };

    let current = null;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderCustomSelect(name, options) {
        const first = options[0] || {value: '', label: 'Select…'};
        const optHtml = options.map((o, i) => {
            const selected = i === 0;
            return `<li role="option" tabindex="-1" aria-selected="${selected ? 'true' : 'false'}"
                data-jm-select-option data-value="${escapeHtml(o.value)}" data-label="${escapeHtml(o.label)}"
                class="flex items-center gap-2 px-2 py-1.5 text-sm rounded-sm cursor-pointer ${selected ? 'bg-brand/10 text-foreground font-medium' : 'text-foreground hover:bg-accent hover:text-accent-foreground'}">
                <span class="w-4 inline-flex justify-center">${selected ? '<i data-lucide=\'check\' class=\'text-[14px] text-brand\'></i>' : ''}</span>
                <span class="truncate">${escapeHtml(o.label)}</span>
            </li>`;
        }).join('');

        const triggerActive = first.value !== '';
        const triggerCls = triggerActive
            ? 'border-brand/40 ring-2 ring-brand/15 bg-brand/5'
            : 'border-input hover:bg-accent/40 hover:border-ring/40';
        const labelCls = triggerActive ? 'font-medium' : 'text-muted-foreground/90';

        return `<div class="relative" data-jm-select>
            <input type="hidden" name="args[${name}]" value="${escapeHtml(first.value)}" data-jm-select-input>
            <button type="button"
                    class="inline-flex items-center justify-between gap-2 h-9 w-full rounded-md border bg-card text-sm px-3 transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring text-foreground ${triggerCls}"
                    data-jm-select-trigger aria-haspopup="listbox" aria-expanded="false">
                <span class="truncate ${labelCls}" data-jm-select-label>${escapeHtml(first.label)}</span>
                <i data-lucide="chevron-down" class="text-[14px] text-muted-foreground shrink-0 transition-transform" data-jm-select-caret></i>
            </button>
            <div class="hidden absolute z-30 mt-1 left-0 min-w-full w-max max-w-[min(20rem,90vw)] rounded-md border border-border bg-popover text-popover-foreground shadow-lg ring-1 ring-black/5 dark:ring-white/5 overflow-hidden"
                 data-jm-select-dropdown role="listbox">
                <ul class="p-1 max-h-60 overflow-y-auto overscroll-contain" data-jm-select-list>${optHtml}</ul>
            </div>
        </div>`;
    }

    function renderArgInput(arg) {
        const required = arg.required ? 'required' : '';
        const defaultVal = arg.default === null ? '' : String(arg.default);
        const placeholder = arg.default !== null ? `default: ${arg.default}` : '';
        const help = arg.help ? `<p class="mt-1 text-xs text-muted-foreground">${escapeHtml(arg.help)}</p>` : '';

        let input;
        switch (arg.type) {
            case 'json_object': {
                const ph = '{"data":{"user_id":42,"email":"new@example.com"}}';
                input = `<textarea name="args[${arg.name}]" rows="3" placeholder="${escapeHtml(ph)}" class="w-full font-mono text-xs rounded-md border border-input bg-card text-foreground px-3 py-2 focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring" ${required}></textarea>`;
                break;
            }
            case 'uuid_list': {
                const ph = 'id1, id2, id3  (comma/space/newline separated — plain text, NOT JSON)';
                input = `<textarea name="args[${arg.name}]" rows="2" placeholder="${escapeHtml(ph)}" class="w-full font-mono text-xs rounded-md border border-input bg-card text-foreground px-3 py-2 focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring" ${required}></textarea>`;
                break;
            }
            case 'fingerprint_list': {
                const ph = '0123456789abcdef, fedcba9876543210  (comma/space/newline — not JSON)';
                input = `<textarea name="args[${arg.name}]" rows="2" placeholder="${escapeHtml(ph)}" class="w-full font-mono text-xs rounded-md border border-input bg-card text-foreground px-3 py-2 focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring" ${required}></textarea>`;
                break;
            }
            case 'email_list': {
                const ph = 'a@example.com, b@example.com  (comma/space/newline — not JSON)';
                input = `<textarea name="args[${arg.name}]" rows="2" placeholder="${escapeHtml(ph)}" class="w-full font-mono text-xs rounded-md border border-input bg-card text-foreground px-3 py-2 focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring" ${required}></textarea>`;
                break;
            }
            case 'int':
                input = `<input type="number" name="args[${arg.name}]" value="${escapeHtml(defaultVal)}" placeholder="${escapeHtml(placeholder)}" class="w-full h-9 rounded-md border border-input bg-card text-foreground px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring" ${required}>`;
                break;
            case 'nullable_bool':
                input = renderCustomSelect(arg.name, [
                    {value: '', label: '(choose)'},
                    {value: 'true', label: 'true'},
                    {value: 'false', label: 'false'},
                    {value: 'null', label: 'null'},
                ]);
                break;
            case 'bool':
                input = renderCustomSelect(arg.name, [
                    {value: '', label: '(choose)'},
                    {value: 'true', label: 'true'},
                    {value: 'false', label: 'false'},
                ]);
                break;
            case 'job_status':
                input = renderCustomSelect(arg.name, [
                    {value: '', label: '(any)'},
                    {value: 'processing', label: 'processing'},
                    {value: 'processed', label: 'processed'},
                    {value: 'failed', label: 'failed'},
                ]);
                break;
            default:
                input = `<input type="text" name="args[${arg.name}]" value="${escapeHtml(defaultVal)}" placeholder="${escapeHtml(placeholder)}" class="w-full h-9 rounded-md border border-input bg-card text-foreground px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring" ${required}>`;
        }

        return `<div>
            <label class="text-xs font-medium text-foreground flex items-center gap-1.5 mb-1">
                <span class="font-mono">${escapeHtml(arg.name)}</span>
                ${arg.required ? '<span class="text-destructive font-bold" title="required">*</span>' : ''}
                <span class="text-muted-foreground font-normal">(${arg.type}${arg.required ? ', required' : ', optional'})</span>
            </label>
            ${input}
            ${help}
        </div>`;
    }

    function selectMethod(btn) {
        document.querySelectorAll('.jm-pg-method').forEach(b => b.classList.remove('bg-accent', 'text-accent-foreground'));
        btn.classList.add('bg-accent', 'text-accent-foreground');

        const args = JSON.parse(btn.dataset.args);
        current = {
            key: btn.dataset.key,
            facade: btn.dataset.facade,
            method: btn.dataset.method,
            destructive: btn.dataset.destructive === '1',
            description: btn.dataset.description,
            returns: btn.dataset.returns,
            args,
        };

        ui.title.textContent = `${current.facade}::${current.method}()`;
        ui.description.textContent = current.description;
        ui.returns.textContent = current.returns;
        ui.methodKey.value = current.key;
        ui.args.innerHTML = args.length === 0
            ? '<p class="text-sm text-muted-foreground italic">This method takes no arguments.</p>'
            : args.map(renderArgInput).join('');
        ui.actions.style.display = '';
        ui.destructiveWarn.style.display = current.destructive ? '' : 'none';
        ui.resultWrap.style.display = 'none';
        if (window.__jmRefreshIcons) window.__jmRefreshIcons();
    }

    function collectArgs() {
        const data = { method: current.key, args: {} };
        for (const el of ui.form.querySelectorAll('[name^="args["]')) {
            const name = el.name.slice(5, -1);
            if (el.value !== '') {
                data.args[name] = el.value;
            }
        }
        return data;
    }

    function validateRequired() {
        if (!current) return ['No method selected.'];
        const errors = [];
        const values = collectArgs().args;
        for (const arg of current.args) {
            if (arg.required && !(arg.name in values)) {
                errors.push(`Required field "${arg.name}" is empty.`);
            }
        }
        return errors;
    }

    function showClientError(message) {
        ui.resultWrap.style.display = '';
        ui.status.textContent = 'validation';
        ui.status.className = 'text-xs px-2 py-0.5 rounded font-mono bg-destructive/10 text-destructive';
        ui.result.textContent = JSON.stringify({error: message, error_class: 'ClientValidation'}, null, 2);
    }

    async function run() {
        const errors = validateRequired();
        if (errors.length > 0) {
            showClientError(errors.join(' '));
            return;
        }
        const payload = collectArgs();
        ui.resultWrap.style.display = '';
        ui.status.textContent = 'running...';
        ui.status.className = 'text-xs px-2 py-0.5 rounded font-mono bg-muted text-muted-foreground';
        ui.result.textContent = '';

        try {
            const res = await fetch(EXECUTE_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
                body: JSON.stringify(payload),
            });
            const body = await res.json();
            ui.status.textContent = `HTTP ${res.status}`;
            ui.status.className = 'text-xs px-2 py-0.5 rounded font-mono ' + (res.ok
                ? 'bg-success/10 text-success'
                : 'bg-destructive/10 text-destructive');
            ui.result.textContent = JSON.stringify(body, null, 2);
        } catch (e) {
            ui.status.textContent = 'network error';
            ui.status.className = 'text-xs px-2 py-0.5 rounded font-mono bg-destructive/10 text-destructive';
            ui.result.textContent = String(e);
        }
    }

    function confirmThenRun() {
        if (!current) return;
        if (!current.destructive) { run(); return; }

        ui.confirmBody.textContent = `About to call ${current.facade}::${current.method}(). This mutates data and cannot be undone from the UI.`;
        ui.confirm.classList.remove('hidden');
        ui.confirm.classList.add('flex');
        if (window.__jmRefreshIcons) window.__jmRefreshIcons();
    }

    function closeConfirm() {
        ui.confirm.classList.add('hidden');
        ui.confirm.classList.remove('flex');
    }

    // wire up
    document.querySelectorAll('.jm-pg-method').forEach(btn => btn.addEventListener('click', () => selectMethod(btn)));
    ui.run.addEventListener('click', confirmThenRun);
    ui.confirmOk.addEventListener('click', () => { closeConfirm(); run(); });
    ui.confirmCancel.addEventListener('click', closeConfirm);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !ui.confirm.classList.contains('hidden')) closeConfirm(); });

    ui.copy.addEventListener('click', () => {
        navigator.clipboard.writeText(ui.result.textContent).catch(() => {});
    });

    ui.search.addEventListener('input', () => {
        const q = ui.search.value.toLowerCase().trim();
        document.querySelectorAll('.jm-pg-method').forEach(btn => {
            const hay = (btn.dataset.facade + '::' + btn.dataset.method + ' ' + btn.dataset.description).toLowerCase();
            btn.closest('li').style.display = hay.includes(q) ? '' : 'none';
        });
    });

    @if($firstMethod)
        // preselect first
        const firstBtn = document.querySelector('.jm-pg-method[data-key="{{ $firstMethod->key }}"]');
        if (firstBtn) selectMethod(firstBtn);
    @endif
})();
</script>
@endsection
