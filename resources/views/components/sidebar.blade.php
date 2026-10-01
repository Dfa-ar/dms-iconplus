@props(['active' => null])

@php
    // route_name null berarti halamannya belum dibuat di tahap ini --
    // ditandai "Segera" di UI, bukan disembunyikan, supaya progres
    // pembangunan frontend tetap terlihat jelas dari sidebar.
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Control Tower', 'route' => 'dashboard', 'icon' => 'tower'],
        ['key' => 'map', 'label' => 'Peta Wilayah', 'route' => 'admin.map.index', 'icon' => 'location'],
        ['key' => 'assignment', 'label' => 'Auto-Assignment', 'route' => 'admin.assignments.index', 'icon' => 'shuffle'],
        ['key' => 'qc', 'label' => 'QC PA', 'route' => 'admin.qc.index', 'icon' => 'check'],
        ['key' => 'qc-reasons', 'label' => 'Alasan Tolak QC', 'route' => 'admin.qc-reject-reasons.index', 'icon' => 'alert'],
        ['key' => 'bast', 'label' => 'BAST', 'route' => 'admin.bast.index', 'icon' => 'document'],
        ['key' => 'payments', 'label' => 'Pembayaran', 'route' => 'admin.payments.index', 'icon' => 'money'],
        ['key' => 'monitoring', 'label' => 'Monitoring', 'route' => 'admin.monitoring.index', 'icon' => 'chart'],
        ['key' => 'pa', 'label' => 'Master Data PA', 'route' => 'admin.pa.index', 'icon' => 'list'],
        ['key' => 'petugas', 'label' => 'Master Petugas', 'route' => 'admin.petugas.index', 'icon' => 'users'],
        ['key' => 'accounts', 'label' => 'Manajemen Akun', 'route' => 'admin.accounts.index', 'icon' => 'key'],
        ['key' => 'kantor', 'label' => 'Kantor Perwakilan', 'route' => 'admin.kantor-perwakilan.index', 'icon' => 'location'],
        ['key' => 'fat', 'label' => 'Master FAT', 'route' => 'admin.fat-points.index', 'icon' => 'location'],
        ['key' => 'splitter', 'label' => 'Master Splitter', 'route' => 'admin.splitters.index', 'icon' => 'network'],
        ['key' => 'aging', 'label' => 'Laporan Aging & SLA', 'route' => 'laporan.aging', 'icon' => 'clock'],
        ['key' => 'audit', 'label' => 'Audit Log', 'route' => 'admin.audit.index', 'icon' => 'list'],
        ['key' => 'reports', 'label' => 'Laporan Excel', 'route' => 'admin.reports.index', 'icon' => 'export'],
        ['key' => 'settings', 'label' => 'Pengaturan', 'route' => 'admin.settings.index', 'icon' => 'sync'],
    ];

    $currentPath = trim(parse_url(request()->url(), PHP_URL_PATH), '/');
    $pathMatchesRoute = function (string $routeName) use ($currentPath) {
        if (! $routeName) {
            return false;
        }

        $targetPath = trim(parse_url(route($routeName), PHP_URL_PATH), '/');

        return $currentPath === $targetPath || str_starts_with($currentPath, $targetPath . '/');
    };
    $activeRoute = collect($navItems)
        ->filter(fn ($item) => $pathMatchesRoute($item['route']))
        ->sortByDesc(fn ($item) => strlen(trim(parse_url(route($item['route']), PHP_URL_PATH), '/')))
        ->first()['route'] ?? null;
@endphp

<aside class="app-sidebar hidden w-[260px] flex-shrink-0 border-r border-white/10 transition-[width] duration-200 lg:flex lg:flex-col" :class="sidebarCollapsed ? 'lg:w-20' : 'lg:w-[260px]'">
    <div class="flex h-20 items-center justify-between gap-1 border-b border-white/10 px-2 font-semibold text-white">
        <div class="flex min-w-0 items-center gap-2">
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-white text-[#0D5A71] shadow-lg">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h7l-1 8 11-14h-7l1-6z" />
            </svg>
            </span>
            <div x-show="! sidebarCollapsed" x-cloak class="min-w-0 leading-tight">
                <p class="text-sm tracking-wide">ICONNET</p>
                <p class="text-[10px] font-normal text-white/55">Deactivation Management</p>
            </div>
        </div>
        <button type="button" @click="toggleSidebar()" :aria-label="sidebarCollapsed ? 'Perlebar sidebar' : 'Perkecil sidebar'" :title="sidebarCollapsed ? 'Perlebar sidebar' : 'Perkecil sidebar'" :aria-expanded="(! sidebarCollapsed).toString()" class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white">
            <svg class="h-4 w-4 transition-transform" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
            </svg>
        </button>
    </div>

    <div x-show="! sidebarCollapsed" x-cloak class="px-5 pt-5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-teal-100 ring-1 ring-white/10">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            NOC Operation
        </span>
    </div>

    <nav class="mt-5 flex-1 space-y-1 px-3">
        @foreach ($navItems as $item)
            @php
                $isActive = $item['route'] === $activeRoute;
                $href = $item['route'] ? route($item['route']) : '#';
            @endphp
            <a
                href="{{ $href }}"
                data-nav-key="{{ $item['key'] }}"
                @if ($isActive) aria-current="page" @endif
                title="{{ $item['label'] }}"
                aria-label="{{ $item['label'] }}"
                @if (! $item['route']) aria-disabled="true" @endif
                :class="sidebarCollapsed ? 'justify-center px-2' : 'justify-between px-3'"
                class="{{ $isActive ? 'nav-active' : '' }} flex min-h-11 items-center rounded-xl py-2.5 text-sm font-medium transition {{ $item['route'] ? '' : 'cursor-not-allowed opacity-40' }}"
            >
                <span class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $isActive ? 'bg-white/10 text-white' : 'bg-white/5 text-white/70' }}">
                        <x-dashboard-icon :name="$item['icon']" class="h-4 w-4" />
                    </span>
                    <span x-show="! sidebarCollapsed" x-cloak class="whitespace-nowrap">{{ $item['label'] }}</span>
                </span>
                @unless ($item['route'])
                    <span x-show="! sidebarCollapsed" x-cloak>
                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] text-white/70">Segera</span>
                    </span>
                @endunless
            </a>
        @endforeach
    </nav>

    <div class="border-t border-slate-100/10 p-4">
        <div x-show="! sidebarCollapsed" x-cloak class="flex items-center gap-2 rounded-xl bg-white/8 px-3 py-2 text-[11px] text-slate-200 ring-1 ring-white/10">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
            Tersambung ke database
        </div>
        <span x-show="sidebarCollapsed" x-cloak title="Tersambung ke database" class="mx-auto block h-2 w-2 rounded-full bg-emerald-400"></span>
    </div>
</aside>
