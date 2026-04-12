<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jobs Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14 items-center">
                <div class="flex items-center gap-6">
                    <div class="flex items-center space-x-2">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span class="font-semibold text-lg">Jobs Monitor</span>
                    </div>
                    @php
                        $navLinks = [
                            ['route' => 'jobs-monitor.dashboard', 'label' => 'Dashboard'],
                            ['route' => 'jobs-monitor.stats', 'label' => 'Stats'],
                        ];
                    @endphp
                    <div class="flex items-center gap-1">
                        @foreach($navLinks as $link)
                            @if(\Illuminate\Support\Facades\Route::has($link['route']))
                                <a href="{{ route($link['route']) }}"
                                   class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ request()->routeIs($link['route']) ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                                    {{ $link['label'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    {{ now()->format('Y-m-d H:i:s') }}
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>
</body>
</html>
