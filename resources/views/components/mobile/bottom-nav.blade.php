@props(['active' => null])

@php
    // route_name null = halaman belum dibuat di tahap ini.
    $navItems = [
        ['key' => 'tasks', 'label' => 'Tugas Hari Ini', 'route' => null, 'icon' => 'list'],
        ['key' => 'history', 'label' => 'Riwayat', 'route' => null, 'icon' => 'clock'],
        ['key' => 'profile', 'label' => 'Profil', 'route' => null, 'icon' => 'users'],
    ];
@endphp

<nav class="fixed bottom-0 left-1/2 w-full max-w-md -translate-x-1/2 border-t border-slate-100 bg-white/95 backdrop-blur">
    <div class="grid grid-cols-3">
        @foreach ($navItems as $item)
            <a
                href="{{ $item['route'] ? route($item['route']) : '#' }}"
                class="flex flex-col items-center gap-1 py-3 text-[11px] font-medium
                    {{ $active === $item['key'] ? 'text-sky-600' : 'text-slate-400' }}"
            >
                <x-dashboard-icon :name="$item['icon']" class="h-5 w-5" />
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
