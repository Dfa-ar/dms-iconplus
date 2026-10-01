@props(['title' => null, 'subtitle' => null])

<header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/85 px-4 backdrop-blur-xl lg:px-7">
    <div class="flex min-h-20 items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <button type="button" @click="drawerOpen = !drawerOpen" class="flex min-h-11 min-w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700 lg:hidden" aria-label="Buka navigasi">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <div>
                @if ($title)
                    <h1 class="text-base font-extrabold tracking-tight text-[#0D5A71]">{{ $title }}</h1>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-slate-400">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        @if (auth()->user()?->role?->name === 'admin')
            <form method="GET" action="{{ route('admin.pa.index') }}" class="hidden max-w-md flex-1 md:block">
                <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <x-dashboard-icon name="search" class="h-4 w-4" />
                </span>
                <input
                    type="text"
                    name="search"
                    placeholder="Cari nomor PA, ID pelanggan, atau pelanggan..."
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-sky-400 focus:bg-white"
                >
                </div>
            </form>
        @endif

        <div class="flex items-center gap-3">
            <button type="button" class="relative flex min-h-11 min-w-11 items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-sky-50 hover:text-sky-600">
                <x-dashboard-icon name="bell" class="h-5 w-5" />
            </button>

            <div class="h-8 w-px bg-slate-200"></div>

            <div class="flex items-center gap-2 rounded-2xl bg-slate-50 px-2 py-1.5 ring-1 ring-slate-200/80">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0D5A71] text-sm font-bold text-white shadow-sm">
                    {{ strtoupper(substr(auth()->user()->name ?? '?', 0, 1)) }}
                </div>
                <div class="hidden text-left leading-tight sm:block">
                    <p class="text-xs font-semibold text-slate-700">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-slate-400">{{ ucfirst(auth()->user()->role?->name ?? '-') }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="ml-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-red-500" title="Keluar">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
