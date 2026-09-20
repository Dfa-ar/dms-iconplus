<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') — Sistem Manajemen Deaktivasi ICONNET</title>

    {{-- Breeze/Vite default -- sesuaikan kalau setup build asset kamu beda --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell antialiased font-sans text-slate-800">
    <div class="min-h-screen lg:grid lg:grid-cols-[0.95fr_1.05fr]">

        {{-- Panel kiri: hero/branding, disembunyikan di layar kecil supaya
             form login tetap yang utama di mobile (mockup Figma memang
             desktop-only untuk panel ini). --}}
        <div class="relative hidden overflow-hidden bg-[#0D5A71] lg:flex">
            <div class="absolute -right-28 -top-20 h-96 w-96 rounded-full border-[42px] border-white/5"></div>
            <div class="absolute -bottom-40 -left-32 h-[28rem] w-[28rem] rounded-full border-[52px] border-[#00AEEF]/10"></div>

            <div class="relative z-10 flex flex-col justify-between p-12 w-full text-white">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 font-semibold tracking-wide">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-[#0D5A71] shadow-lg">
                                <svg class="h-4 w-4 text-teal-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h7l-1 8 11-14h-7l1-6z" />
                                </svg>
                            </span>
                            <span><strong>ICONNET</strong><small class="block text-[10px] font-normal text-white/60">Deactivation Management System</small></span>
                        </div>
                        <span class="text-[11px] uppercase tracking-widest text-teal-300/80 border border-teal-400/30 rounded-full px-3 py-1">
                            Operasional Terpadu
                        </span>
                    </div>

                    <span class="mt-10 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] uppercase tracking-widest text-slate-200">
                        <svg class="h-3 w-3 text-teal-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        PLN Icon Plus · Operasional Lapangan
                    </span>

                    <h1 class="mt-6 text-4xl font-bold leading-tight">
                        Sistem Manajemen
                        <span class="text-[#FFF200]">Deaktivasi ICONNET</span>
                    </h1>

                    <p class="mt-4 max-w-md text-sm text-slate-300">
                        Satu ruang kerja untuk upload PA, pembagian tugas, eksekusi petugas, dan pemantauan SLA.
                    </p>

                    <div class="mt-8 grid grid-cols-3 gap-4">
                        <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-sm font-semibold">Auto-Assignment</p>
                            <p class="mt-1 text-xs text-slate-300">Disposisi tugas otomatis ke petugas lapangan berbasis rayon.</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-sm font-semibold">SLA Tracking</p>
                            <p class="mt-1 text-xs text-slate-300">Pemantauan aging & prioritas tiket secara real-time.</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-sm font-semibold">Bukti Foto & BAP</p>
                            <p class="mt-1 text-xs text-slate-300">Validasi foto perangkat & berita acara serah terima.</p>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-slate-400">
                    Sistem internal untuk koordinasi deaktivasi dan penarikan perangkat ICONNET.
                </p>
            </div>
        </div>

        {{-- Panel kanan: form login --}}
        <div class="flex w-full items-center justify-center px-6 py-10 lg:px-16">
            <div class="w-full max-w-md" data-reveal>
                <div class="mb-8 flex items-center gap-2 text-xs font-medium text-slate-500 lg:hidden">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-900 text-teal-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h7l-1 8 11-14h-7l1-6z" />
                        </svg>
                    </span>
                    Sistem Manajemen Deaktivasi ICONNET
                </div>

                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
