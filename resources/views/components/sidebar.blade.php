@props(['active' => null])

@php
    // route_name null berarti halamannya belum dibuat di tahap ini --
    // ditandai "Segera" di UI, bukan disembunyikan, supaya progres
    // pembangunan frontend tetap terlihat jelas dari sidebar.
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Control Tower', 'route' => 'dashboard', 'icon' => 'tower'],
        ['key' => 'assignment', 'label' => 'Auto-Assignment', 'route' => 'admin.assignments.index', 'icon' => 'shuffle'],
        ['key' => 'pa', 'label' => 'Master Data PA', 'route' => 'admin.pa.index', 'icon' => 'list'],
        ['key' => 'petugas', 'label' => 'Master Petugas', 'route' => 'admin.petugas.index', 'icon' => 'users'],
        ['key' => 'aging', 'label' => 'Laporan Aging & SLA', 'route' => 'laporan.aging', 'icon' => 'clock'],
    ];
@endphp

<aside class="app-sidebar hidden w-[260px] flex-shrink-0 border-r border-white/10 lg:flex lg:flex-col">
    <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6 font-semibold text-white">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-[#0D5A71] shadow-lg">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h7l-1 8 11-14h-7l1-6z" />
            </svg>
        </span>
        <div class="leading-tight">
            <p class="text-sm tracking-wide">ICONNET</p>
            <p class="text-[10px] font-normal text-white/55">Deactivation Management</p>
        </div>
    </div>

    <div class="px-5 pt-5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-teal-100">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            NOC Operation
        </span>
    </div>

    <nav class="mt-5 flex-1 space-y-1 px-3">
        @foreach ($navItems as $item)
            @php
                $isActive = $active === $item['key'];
                $href = $item['route'] ? route($item['route']) : '#';
            @endphp
            <a
                href="{{ $href }}"
                @if (! $item['route']) aria-disabled="true" @endif
                class="{{ $isActive ? 'nav-active' : '' }} flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-sm font-medium transition {{ $item['route'] ? '' : 'cursor-not-allowed opacity-40' }}"
            >
                <span class="flex items-center gap-2.5">
                    <x-dashboard-icon :name="$item['icon']" class="h-4 w-4" />
                    {{ $item['label'] }}
                </span>
                @unless ($item['route'])
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-400">Segera</span>
                @endunless
            </a>
        @endforeach
    </nav>

    <div class="border-t border-slate-100 p-4">
        <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            Tersambung ke database
        </div>
    </div>
</aside>
