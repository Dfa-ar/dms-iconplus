@props(['active' => null])

@php
    $navItems = [
        ['key' => 'tasks', 'label' => 'Tugas', 'route' => 'petugas.tasks.index', 'icon' => 'list'],
        ['key' => 'report', 'label' => 'Laporan', 'route' => 'petugas.tasks.report', 'icon' => 'check'],
        ['key' => 'history', 'label' => 'Riwayat', 'route' => 'petugas.tasks.history', 'icon' => 'clock'],
        ['key' => 'profile', 'label' => 'Profil', 'route' => 'petugas.profile', 'icon' => 'users'],
    ];
@endphp

<nav class="fixed bottom-0 left-1/2 w-full max-w-md -translate-x-1/2 border-t border-slate-100 bg-white/95 backdrop-blur">
    <div class="flex w-full items-stretch">
        @foreach ($navItems as $item)
            <a
                href="{{ route($item['route']) }}"
                class="flex flex-1 items-center justify-center gap-1.5 py-3 text-[11px] font-medium
                    {{ $active === $item['key'] ? 'text-sky-600' : 'text-slate-400' }}"
            >
                <x-dashboard-icon :name="$item['icon']" class="h-4 w-4" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
