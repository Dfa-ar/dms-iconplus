@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <div class="mb-6 inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" />
        </svg>
        Portal Pegawai
    </div>

    <h1 class="text-3xl font-extrabold tracking-tight text-[#0D5A71]">Selamat datang kembali</h1>
    <p class="mt-1 text-sm text-slate-500">
        Masuk dengan akun korporat untuk melanjutkan pekerjaan operasional Anda.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.submit') }}" class="mt-6 space-y-5" x-data="{ showPassword: false }">
        @csrf

        {{-- Peran Operasional: kemudahan UX saja, otorisasi sesungguhnya
             tetap dicek dari role akun di database -- lihat komentar di
             LoginController::store(). --}}
        <div>
            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                Peran Operasional
            </label>
            <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1 text-xs font-medium">
                @foreach (['supervisor' => 'Supervisor', 'admin' => 'Admin / PIC', 'petugas' => 'Petugas Lapangan'] as $value => $label)
                    <label class="relative">
                        <input
                            type="radio"
                            name="role_tab"
                            value="{{ $value }}"
                            class="peer sr-only"
                            {{ old('role_tab', 'dispatcher_noc') === $value ? 'checked' : '' }}
                        >
                        <span class="flex cursor-pointer items-center justify-center rounded-md py-2 text-center text-slate-500 peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('role_tab')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="identifier" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                Email Perusahaan / NIP Petugas
            </label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </span>
                <input
                    id="identifier"
                    type="text"
                    name="identifier"
                    value="{{ old('identifier') }}"
                    placeholder="misal: 9418293740 atau nama@perusahaan.co.id"
                    class="w-full rounded-lg border border-slate-200 py-2.5 pl-10 pr-3 text-sm placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    autofocus
                >
            </div>
            <p class="mt-1.5 text-xs text-slate-400">Gunakan email yang terdaftar di sistem, atau NIP kalau akun Anda petugas lapangan.</p>
            @error('identifier')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">
                    Kata Sandi
                </label>
                {{-- [ASUMSI] belum ada alur reset password di scope Phase 1 --}}
                        <span class="text-xs text-slate-300" title="Hubungi Admin untuk reset password">Lupa kata sandi?</span>
            </div>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" />
                    </svg>
                </span>
                <input
                    id="password"
                    type="password"
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    placeholder="Masukkan kata sandi akun"
                    class="w-full rounded-lg border border-slate-200 py-2.5 pl-10 pr-10 text-sm placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                >
                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between text-xs text-slate-500">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                Ingat saya di perangkat ini
            </label>
            <span class="inline-flex items-center gap-1 text-emerald-600">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Terenkripsi
            </span>
        </div>

        <button type="submit" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#00AEEF] py-2.5 text-sm font-extrabold text-white shadow-lg shadow-sky-500/20 transition hover:-translate-y-0.5 hover:bg-[#009bd5]">
            Masuk ke sistem
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
        </button>
    </form>

    <div class="mt-6 flex items-center gap-3 text-xs text-slate-400">
        <span class="h-px flex-1 bg-slate-200"></span>
        Atau
        <span class="h-px flex-1 bg-slate-200"></span>
    </div>

    {{-- SSO belum diimplementasikan (di luar scope Blueprint Phase 1) --
         disabled dan dijelaskan lewat title, bukan dihilangkan sama sekali,
         supaya tetap terlihat sesuai mockup tapi tidak menjanjikan fitur
         yang belum ada. --}}
    <button type="button" disabled title="Belum tersedia -- Phase 1 hanya login email/NIP + password" class="mt-4 flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-slate-200 py-2.5 text-sm font-medium text-slate-400">
        Single Sign-On (SSO) — Segera Hadir
    </button>

    <div class="mt-6 flex gap-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Petugas lapangan login memakai NIP dan kata sandi akun yang dibuatkan Admin — hubungi Admin kalau belum punya akun.
    </div>
@endsection
