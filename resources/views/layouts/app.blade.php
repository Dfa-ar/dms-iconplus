<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <title>@yield('title', 'Dashboard') — IDMS ICONNET</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}'));
        }
    </script>
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased" x-data="{ drawerOpen: false }">
    <div class="app-shell flex min-h-screen overflow-hidden">
        <x-sidebar :active="$active ?? null" />
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-40 lg:hidden" @keydown.escape.window="drawerOpen = false">
            <div class="absolute inset-0 bg-slate-950/40" @click="drawerOpen = false"></div>
            <aside class="app-sidebar relative h-full w-[min(82vw,320px)] p-4 shadow-2xl" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0">
                <div class="flex items-center justify-between border-b border-white/10 px-2 pb-5 text-white"><span class="font-bold">ICONNET DMS</span><button @click="drawerOpen = false" class="flex min-h-11 min-w-11 items-center justify-center rounded-xl bg-white/10" aria-label="Tutup navigasi">&times;</button></div>
                <nav class="mt-5 space-y-1">@foreach ([['label' => 'Control Tower', 'route' => 'dashboard'], ['label' => 'Master Data PA', 'route' => 'admin.pa.index'], ['label' => 'Auto-Assignment', 'route' => 'admin.assignments.index'], ['label' => 'Laporan Aging & SLA', 'route' => 'laporan.aging']] as $item)<a href="{{ route($item['route']) }}" class="flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-white/75 hover:bg-white/10 hover:text-white">{{ $item['label'] }}</a>@endforeach</nav>
            </aside>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <x-topbar :title="$title ?? null" :subtitle="$subtitle ?? null" />

            <main class="app-main flex-1 p-4 lg:p-7" data-reveal>
                {{-- Flash message umum: back()->with('status', '...') dari
                     controller mana pun (mis. PaOrderController::correct,
                     AssignmentController::generate/reassign). --}}
                @if (session('status'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
    @if (auth()->user()?->role?->name === 'petugas')
        <nav class="mobile-bottom-nav fixed inset-x-0 bottom-0 z-30 flex items-center justify-around border-t border-slate-200 bg-white/95 px-3 pt-3 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur lg:hidden">
            <a href="{{ route('petugas.tasks.index') }}" class="flex min-h-11 min-w-20 flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold {{ ($active ?? '') === 'dashboard' ? 'text-sky-600' : 'text-slate-400' }}"><x-dashboard-icon name="list" class="h-5 w-5" />Tugas Saya</a>
            <a href="{{ route('petugas.tasks.history') }}" class="flex min-h-11 min-w-20 flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold {{ ($active ?? '') === 'history' ? 'text-sky-600' : 'text-slate-400' }}"><x-dashboard-icon name="clock" class="h-5 w-5" />Riwayat</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex min-h-11 min-w-20 flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold text-slate-400"><x-dashboard-icon name="logout" class="h-5 w-5" />Keluar</button></form>
        </nav>
    @endif
    @stack('scripts')
</body>
</html>
