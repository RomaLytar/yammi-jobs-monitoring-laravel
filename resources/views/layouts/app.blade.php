<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Favicon mirrors the gradient "activity" glyph used in the top-left logo. --}}
    <link rel="icon" type="image/svg+xml"
          href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%238b5cf6'/%3E%3Cstop offset='1' stop-color='%236d28d9'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='32' height='32' rx='7' fill='url(%23g)'/%3E%3Cpath d='M6 17 h4.5 l2.5-7 l4 14 l3-7 h5.5' fill='none' stroke='white' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <title>Yammi — Queue observability</title>

    {{-- Apply theme before paint to prevent flash --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('jm-theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter var', 'Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
                    },
                    colors: {
                        border: 'hsl(var(--border))',
                        input: 'hsl(var(--input))',
                        ring: 'hsl(var(--ring))',
                        background: 'hsl(var(--background))',
                        foreground: 'hsl(var(--foreground))',
                        primary: {
                            DEFAULT: 'hsl(var(--primary))',
                            foreground: 'hsl(var(--primary-foreground))',
                        },
                        secondary: {
                            DEFAULT: 'hsl(var(--secondary))',
                            foreground: 'hsl(var(--secondary-foreground))',
                        },
                        destructive: {
                            DEFAULT: 'hsl(var(--destructive))',
                            foreground: 'hsl(var(--destructive-foreground))',
                        },
                        success: {
                            DEFAULT: 'hsl(var(--success))',
                            foreground: 'hsl(var(--success-foreground))',
                        },
                        warning: {
                            DEFAULT: 'hsl(var(--warning))',
                            foreground: 'hsl(var(--warning-foreground))',
                        },
                        info: {
                            DEFAULT: 'hsl(var(--info))',
                            foreground: 'hsl(var(--info-foreground))',
                        },
                        muted: {
                            DEFAULT: 'hsl(var(--muted))',
                            foreground: 'hsl(var(--muted-foreground))',
                        },
                        accent: {
                            DEFAULT: 'hsl(var(--accent))',
                            foreground: 'hsl(var(--accent-foreground))',
                        },
                        popover: {
                            DEFAULT: 'hsl(var(--popover))',
                            foreground: 'hsl(var(--popover-foreground))',
                        },
                        card: {
                            DEFAULT: 'hsl(var(--card))',
                            foreground: 'hsl(var(--card-foreground))',
                        },
                        brand: {
                            DEFAULT: 'hsl(var(--brand))',
                            foreground: 'hsl(var(--brand-foreground))',
                        },
                    },
                    borderRadius: {
                        xl: 'calc(var(--radius) + 4px)',
                        lg: 'var(--radius)',
                        md: 'calc(var(--radius) - 2px)',
                        sm: 'calc(var(--radius) - 4px)',
                    },
                    boxShadow: {
                        xs: '0 1px 2px 0 rgb(0 0 0 / 0.04)',
                        glow: '0 0 0 1px hsl(var(--ring) / 0.15), 0 8px 24px -8px hsl(var(--ring) / 0.25)',
                    },
                    keyframes: {
                        'fade-in': { from: { opacity: '0', transform: 'translateY(-2px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
                        'slide-down': { from: { opacity: '0', transform: 'translateY(-6px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
                        'pulse-soft': { '0%, 100%': { opacity: '1' }, '50%': { opacity: '0.55' } },
                        'shimmer': { '0%': { backgroundPosition: '-200% 0' }, '100%': { backgroundPosition: '200% 0' } },
                    },
                    animation: {
                        'fade-in': 'fade-in .2s ease-out',
                        'slide-down': 'slide-down .22s ease-out',
                        'pulse-soft': 'pulse-soft 1.8s ease-in-out infinite',
                        'shimmer': 'shimmer 2s linear infinite',
                    },
                },
            },
        };
    </script>

    {{-- Lucide icons --}}
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    {{-- Inter font --}}
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 240 10% 3.9%;
            --card: 0 0% 100%;
            --card-foreground: 240 10% 3.9%;
            --popover: 0 0% 100%;
            --popover-foreground: 240 10% 3.9%;
            --primary: 240 5.9% 10%;
            --primary-foreground: 0 0% 98%;
            --secondary: 240 4.8% 95.9%;
            --secondary-foreground: 240 5.9% 10%;
            --muted: 240 4.8% 95.9%;
            --muted-foreground: 240 3.8% 46.1%;
            --accent: 240 4.8% 95.9%;
            --accent-foreground: 240 5.9% 10%;
            --destructive: 0 72% 51%;
            --destructive-foreground: 0 0% 98%;
            --success: 142 71% 36%;
            --success-foreground: 0 0% 98%;
            --warning: 35 92% 45%;
            --warning-foreground: 48 96% 8%;
            --info: 217 91% 55%;
            --info-foreground: 0 0% 98%;
            --border: 240 5.9% 90%;
            --input: 240 5.9% 90%;
            --ring: 240 5.9% 10%;
            --brand: 262 83% 58%;
            --brand-foreground: 0 0% 100%;
            --radius: 0.625rem;
        }
        .dark {
            --background: 240 10% 4%;
            --foreground: 0 0% 98%;
            --card: 240 6% 7%;
            --card-foreground: 0 0% 98%;
            --popover: 240 6% 7%;
            --popover-foreground: 0 0% 98%;
            --primary: 0 0% 98%;
            --primary-foreground: 240 5.9% 10%;
            --secondary: 240 3.7% 15.9%;
            --secondary-foreground: 0 0% 98%;
            --muted: 240 3.7% 13%;
            --muted-foreground: 240 5% 65%;
            --accent: 240 3.7% 15.9%;
            --accent-foreground: 0 0% 98%;
            --destructive: 0 72% 55%;
            --destructive-foreground: 0 0% 98%;
            --success: 142 71% 45%;
            --success-foreground: 144 80% 8%;
            --warning: 35 92% 55%;
            --warning-foreground: 48 96% 8%;
            --info: 217 91% 60%;
            --info-foreground: 210 40% 98%;
            --border: 240 3.7% 16%;
            --input: 240 3.7% 18%;
            --ring: 240 4.9% 83.9%;
            --brand: 263 75% 70%;
            --brand-foreground: 240 10% 4%;
        }

        html, body { font-family: 'Inter var', 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif; }
        body { font-feature-settings: 'cv02','cv03','cv04','cv11'; -webkit-font-smoothing: antialiased; }
        [x-cloak] { display: none !important; }

        /* Grid backdrop for hero/empty states */
        .bg-grid {
            background-image:
                linear-gradient(to right, hsl(var(--border) / 0.5) 1px, transparent 1px),
                linear-gradient(to bottom, hsl(var(--border) / 0.5) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* Scrollbar */
        *::-webkit-scrollbar { width: 10px; height: 10px; }
        *::-webkit-scrollbar-track { background: transparent; }
        *::-webkit-scrollbar-thumb {
            background: hsl(var(--border));
            border-radius: 10px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        *::-webkit-scrollbar-thumb:hover {
            background: hsl(var(--muted-foreground) / 0.5);
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        /* Icon sizing helper for Lucide */
        [data-lucide] { width: 1em; height: 1em; stroke-width: 2; }

        /* Branded checkbox — checkmark and indeterminate dash drawn as SVG
           backgrounds. The colour is baked into the data URI so it stays
           visible on the checked-primary background in either theme. */
        input[type="checkbox"].jm-checkbox:checked {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 12' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='2 6 5.5 9.5 12 3'/%3E%3C/svg%3E");
            background-size: 72% 72%;
        }
        input[type="checkbox"].jm-checkbox:indeterminate {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 12' fill='none' stroke='white' stroke-width='2' stroke-linecap='round'%3E%3Cline x1='3' y1='6' x2='11' y2='6'/%3E%3C/svg%3E");
            background-size: 72% 72%;
        }
        .dark input[type="checkbox"].jm-checkbox:checked,
        .dark input[type="checkbox"].jm-checkbox:indeterminate {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 12' fill='none' stroke='%2318181b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='2 6 5.5 9.5 12 3'/%3E%3C/svg%3E");
        }
        .dark input[type="checkbox"].jm-checkbox:indeterminate {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 12' fill='none' stroke='%2318181b' stroke-width='2' stroke-linecap='round'%3E%3Cline x1='3' y1='6' x2='11' y2='6'/%3E%3C/svg%3E");
        }

        /* Custom select — chevron is theme-aware (data-URIs can't use currentColor) */
        select.jm-select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            /* light: muted-foreground ≈ #737379 */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23737379' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.6rem center;
            background-size: 1rem;
            padding-right: 2.1rem;
            cursor: pointer;
        }
        select.jm-select:hover {
            border-color: hsl(var(--ring) / 0.45);
            background-color: hsl(var(--accent) / 0.4);
        }
        /* dark: muted-foreground ≈ #a1a1aa */
        .dark select.jm-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23a1a1aa' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 4 4 4-4'/%3E%3C/svg%3E");
        }
        /* Option list (Chromium/Firefox respect these; Safari ignores most) */
        select.jm-select option {
            background-color: hsl(var(--popover));
            color: hsl(var(--popover-foreground));
            padding: 0.5rem 0.75rem;
        }
        select.jm-select option:checked,
        select.jm-select option:hover {
            background-color: hsl(var(--accent));
            color: hsl(var(--accent-foreground));
        }
        /* Firefox: hide default focus ring (we render our own via ring-*) */
        select.jm-select:-moz-focusring {
            color: transparent;
            text-shadow: 0 0 0 hsl(var(--foreground));
        }
    </style>
</head>
<body class="bg-background text-foreground min-h-screen antialiased">

    {{-- Ambient background glow --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[420px] overflow-hidden">
        <div class="absolute left-1/2 top-[-140px] h-[420px] w-[900px] -translate-x-1/2 rounded-full bg-brand/10 blur-3xl"></div>
    </div>

    <nav class="sticky top-0 z-40 backdrop-blur-md bg-background/75 border-b border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14 items-center gap-4">
                <div class="flex items-center gap-6 min-w-0">
                    <a href="{{ route('jobs-monitor.dashboard') }}" class="flex items-center gap-2.5 shrink-0">
                        <div class="relative flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand to-brand/70 text-brand-foreground shadow-sm ring-1 ring-inset ring-white/10">
                            <i data-lucide="activity" class="text-[15px]"></i>
                            <span class="absolute -right-0.5 -top-0.5 flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-pulse-soft rounded-full bg-success opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-success"></span>
                            </span>
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span class="font-semibold text-sm tracking-tight bg-gradient-to-r from-foreground to-brand bg-clip-text text-transparent">Yammi</span>
                            <span class="text-[10px] text-muted-foreground -mt-0.5">Queue observability</span>
                        </div>
                    </a>

                    @php
                        $navItems = [
                            ['route' => 'jobs-monitor.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                            ['route' => 'jobs-monitor.stats',     'label' => 'Stats',     'icon' => 'bar-chart-3'],
                            [
                                'label' => 'Failures',
                                'icon' => 'alert-triangle',
                                'children' => [
                                    ['route' => 'jobs-monitor.failures.groups.page', 'label' => 'Groups',    'icon' => 'fingerprint'],
                                    ['route' => 'jobs-monitor.dlq',                  'label' => 'DLQ',       'icon' => 'skull'],
                                ],
                            ],
                            [
                                'label' => 'Monitoring',
                                'icon' => 'activity',
                                'children' => [
                                    ['route' => 'jobs-monitor.scheduled', 'label' => 'Scheduled', 'icon' => 'calendar-clock'],
                                    ['route' => 'jobs-monitor.anomalies', 'label' => 'Anomalies', 'icon' => 'trending-down'],
                                    ['route' => 'jobs-monitor.workers',   'label' => 'Workers',   'icon' => 'cpu'],
                                ],
                            ],
                            ['route' => 'jobs-monitor.settings', 'label' => 'Settings', 'icon' => 'settings'],
                        ];

                        $navLinkClass = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md transition-colors';
                        $navLinkActive = 'bg-secondary text-foreground shadow-xs';
                        $navLinkIdle = 'text-muted-foreground hover:text-foreground hover:bg-accent';

                        // Flat list for mobile nav (unchanged horizontal scroll).
                        $navLinksFlat = [];
                        foreach ($navItems as $item) {
                            if (isset($item['children'])) {
                                foreach ($item['children'] as $child) {
                                    $navLinksFlat[] = $child;
                                }
                            } else {
                                $navLinksFlat[] = $item;
                            }
                        }
                    @endphp
                    <div class="hidden md:flex items-center gap-0.5">
                        @foreach($navItems as $item)
                            @if(isset($item['children']))
                                @php
                                    $groupActive = false;
                                    foreach ($item['children'] as $child) {
                                        if (\Illuminate\Support\Facades\Route::has($child['route']) && request()->routeIs($child['route'])) {
                                            $groupActive = true;
                                            break;
                                        }
                                    }
                                @endphp
                                <div class="relative" data-jm-nav-dropdown>
                                    <button type="button"
                                            class="{{ $navLinkClass }} gap-1 {{ $groupActive ? $navLinkActive : $navLinkIdle }}"
                                            data-jm-nav-trigger>
                                        <i data-lucide="{{ $item['icon'] }}" class="text-[14px] {{ $groupActive ? 'text-brand' : '' }}"></i>
                                        {{ $item['label'] }}
                                        <i data-lucide="chevron-down" class="text-[12px] opacity-60"></i>
                                    </button>
                                    <div class="hidden absolute left-0 top-full mt-1 z-50 min-w-[10rem] rounded-lg border border-border bg-popover shadow-lg ring-1 ring-black/5 dark:ring-white/5 py-1"
                                         data-jm-nav-panel>
                                        @foreach($item['children'] as $child)
                                            @if(\Illuminate\Support\Facades\Route::has($child['route']))
                                                @php $childActive = request()->routeIs($child['route']); @endphp
                                                <a href="{{ route($child['route']) }}"
                                                   class="flex items-center gap-2 px-3 py-2 text-sm transition-colors
                                                          {{ $childActive
                                                              ? 'bg-accent text-foreground font-medium'
                                                              : 'text-muted-foreground hover:text-foreground hover:bg-accent' }}">
                                                    <i data-lucide="{{ $child['icon'] }}" class="text-[14px] {{ $childActive ? 'text-brand' : '' }}"></i>
                                                    {{ $child['label'] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                @if(\Illuminate\Support\Facades\Route::has($item['route']))
                                    @php $isActive = request()->routeIs($item['route']); @endphp
                                    <a href="{{ route($item['route']) }}"
                                       class="{{ $navLinkClass }} {{ $isActive ? $navLinkActive : $navLinkIdle }}">
                                        <i data-lucide="{{ $item['icon'] }}" class="text-[14px] {{ $isActive ? 'text-brand' : '' }}"></i>
                                        {{ $item['label'] }}
                                    </a>
                                @endif
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="hidden sm:inline-flex items-center gap-1.5 h-8 text-xs font-mono tabular-nums text-muted-foreground px-2.5 rounded-md border border-border bg-card/50">
                        <span class="h-1.5 w-1.5 rounded-full bg-success animate-pulse-soft"></span>
                        <span data-live-clock>{{ now()->format('Y-m-d H:i:s') }}</span>
                    </span>
                    <button type="button"
                            onclick="__jmToggleTheme()"
                            aria-label="Toggle theme"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                        <i data-lucide="sun" class="text-[15px] block dark:hidden"></i>
                        <i data-lucide="moon" class="text-[15px] hidden dark:block"></i>
                    </button>
                </div>
            </div>

            {{-- Mobile nav --}}
            <div class="md:hidden flex items-center gap-1 overflow-x-auto pb-2 -mx-1 px-1">
                @foreach($navLinksFlat as $link)
                    @if(\Illuminate\Support\Facades\Route::has($link['route']))
                        @php $isActive = request()->routeIs($link['route']); @endphp
                        <a href="{{ route($link['route']) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md whitespace-nowrap
                                  {{ $isActive ? 'bg-secondary text-foreground' : 'text-muted-foreground hover:bg-accent' }}">
                            <i data-lucide="{{ $link['icon'] }}" class="text-[14px]"></i>
                            {{ $link['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 animate-fade-in">
        @yield('content')
    </main>

    <script>
        function __jmToggleTheme() {
            var isDark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('jm-theme', isDark ? 'dark' : 'light'); } catch (e) {}
        }

        // Hydrate Lucide icons; re-run after dynamic DOM updates
        (function () {
            function run() {
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
            window.__jmRefreshIcons = run;
        })();

        // Custom <select> popover
        (function () {
            function closeAll(except) {
                document.querySelectorAll('[data-jm-select]').forEach(function (root) {
                    if (root === except) return;
                    var dd = root.querySelector('[data-jm-select-dropdown]');
                    var tr = root.querySelector('[data-jm-select-trigger]');
                    var ct = root.querySelector('[data-jm-select-caret]');
                    if (dd) dd.classList.add('hidden');
                    if (tr) tr.setAttribute('aria-expanded', 'false');
                    if (ct) ct.style.transform = '';
                });
            }
            function openDropdown(root) {
                closeAll(root);
                var dd = root.querySelector('[data-jm-select-dropdown]');
                var tr = root.querySelector('[data-jm-select-trigger]');
                var ct = root.querySelector('[data-jm-select-caret]');
                dd.classList.remove('hidden');
                tr.setAttribute('aria-expanded', 'true');
                if (ct) ct.style.transform = 'rotate(180deg)';
                var selected = dd.querySelector('[aria-selected="true"]');
                (selected || dd.querySelector('[data-jm-select-option]'))?.focus?.();
            }
            function pick(root, opt) {
                var input = root.querySelector('[data-jm-select-input]');
                var label = root.querySelector('[data-jm-select-label]');
                var value = opt.getAttribute('data-value');
                var text = opt.getAttribute('data-label');
                if (input) input.value = value;
                if (label) {
                    label.textContent = text;
                    label.classList.toggle('font-medium', value !== '');
                    label.classList.toggle('text-muted-foreground/90', value === '');
                }
                root.querySelectorAll('[data-jm-select-option]').forEach(function (o) {
                    var isIt = o === opt;
                    o.setAttribute('aria-selected', isIt ? 'true' : 'false');
                    o.classList.toggle('bg-brand/10', isIt);
                    o.classList.toggle('font-medium', isIt);
                    o.classList.toggle('hover:bg-accent', !isIt);
                    var tick = o.querySelector('span [data-lucide]');
                    if (isIt && !tick) {
                        var slot = o.querySelector('span');
                        if (slot) {
                            slot.innerHTML = '<i data-lucide="check" class="text-[14px] text-brand"></i>';
                            if (window.__jmRefreshIcons) window.__jmRefreshIcons();
                        }
                    } else if (!isIt && tick) {
                        tick.remove();
                    }
                });
                // visually mark trigger as "active" when a value is selected
                var tr = root.querySelector('[data-jm-select-trigger]');
                if (tr) {
                    var active = value !== '';
                    tr.classList.toggle('border-brand/40', active);
                    tr.classList.toggle('ring-2', active);
                    tr.classList.toggle('ring-brand/15', active);
                    tr.classList.toggle('bg-brand/5', active);
                    tr.classList.toggle('border-input', !active);
                }
                closeAll(null);
                input?.dispatchEvent(new Event('change', { bubbles: true }));
            }

            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-jm-select-trigger]');
                if (trigger) {
                    var root = trigger.closest('[data-jm-select]');
                    var dd = root.querySelector('[data-jm-select-dropdown]');
                    if (dd.classList.contains('hidden')) openDropdown(root); else closeAll(null);
                    e.stopPropagation();
                    return;
                }
                var opt = e.target.closest('[data-jm-select-option]');
                if (opt) {
                    pick(opt.closest('[data-jm-select]'), opt);
                    e.stopPropagation();
                    return;
                }
                closeAll(null);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closeAll(null); return; }
                var root = document.activeElement?.closest?.('[data-jm-select]');
                if (!root) return;
                var dd = root.querySelector('[data-jm-select-dropdown]');
                if (dd.classList.contains('hidden')) {
                    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openDropdown(root);
                    }
                    return;
                }
                var opts = Array.from(dd.querySelectorAll('[data-jm-select-option]'));
                var idx = opts.indexOf(document.activeElement);
                if (e.key === 'ArrowDown') { e.preventDefault(); (opts[Math.min(opts.length - 1, idx + 1)] || opts[0]).focus(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); (opts[Math.max(0, idx - 1)] || opts[0]).focus(); }
                else if (e.key === 'Enter' || e.key === ' ') {
                    if (idx >= 0) { e.preventDefault(); pick(root, opts[idx]); }
                }
            });
        })();

        // Nav dropdowns
        (function () {
            function closeAllNav(except) {
                document.querySelectorAll('[data-jm-nav-dropdown]').forEach(function (root) {
                    if (root === except) return;
                    var panel = root.querySelector('[data-jm-nav-panel]');
                    if (panel) panel.classList.add('hidden');
                });
            }

            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-jm-nav-trigger]');
                if (trigger) {
                    var root = trigger.closest('[data-jm-nav-dropdown]');
                    var panel = root.querySelector('[data-jm-nav-panel]');
                    var wasHidden = panel.classList.contains('hidden');
                    closeAllNav(root);
                    if (wasHidden) { panel.classList.remove('hidden'); } else { panel.classList.add('hidden'); }
                    e.stopPropagation();
                    return;
                }
                if (!e.target.closest('[data-jm-nav-panel]')) {
                    closeAllNav(null);
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeAllNav(null);
            });
        })();

        // Live clock
        (function () {
            function pad(n) { return n < 10 ? '0' + n : '' + n; }
            function tick() {
                var nodes = document.querySelectorAll('[data-live-clock]');
                if (!nodes.length) return;
                var d = new Date();
                var s = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
                      + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                nodes.forEach(function (n) { n.textContent = s; });
            }
            function start() { tick(); setInterval(tick, 1000); }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start);
            } else {
                start();
            }
        })();
    </script>
</body>
</html>
