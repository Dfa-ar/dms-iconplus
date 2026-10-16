@extends('layouts.petugas')

@php
    $active = 'dashboard';
    $title = 'Tugas Saya';
    $subtitle = 'Eksekusi deaktivasi hari ini';
    $completion = $target > 0 ? round(($progress / $target) * 100) : 0;
    $routeData = $routeData ?? [];
@endphp

@section('title', 'Tugas Saya')

@section('content')
    <div class="mx-auto w-full max-w-[440px] lg:mx-0 lg:max-w-none" data-reveal>
        <section class="relative overflow-hidden rounded-[24px] bg-[#0D5A71] p-5 text-white shadow-xl shadow-cyan-950/10 sm:p-6">
            <div class="pointer-events-none absolute -right-12 -top-16 h-48 w-48 rounded-full border-[24px] border-white/5"></div>
            <div class="pointer-events-none absolute bottom-0 left-0 h-32 w-32 rounded-full bg-white/5 blur-2xl"></div>

            <div class="relative z-10 flex items-start justify-between gap-4">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/70">Operasional hari ini</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight">Tugas Saya</h2>
                    <p class="mt-1 text-sm text-cyan-50/70">{{ now()->translatedFormat('l, d F Y') }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-3 py-2 text-right ring-1 ring-white/10 backdrop-blur">
                    <p class="text-2xl font-extrabold">{{ $progress }}<span class="text-base font-medium text-cyan-100/60">/{{ $target }}</span></p>
                    <p class="text-[10px] uppercase tracking-wide text-cyan-100/70">Selesai</p>
                </div>
            </div>

            <div class="relative z-10 mt-7">
                <div class="mb-2 flex justify-between text-xs font-semibold text-cyan-50/80">
                    <span>Progress pekerjaan</span>
                    <span>{{ $completion }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-white/15">
                    <div class="h-full rounded-full bg-[#FFF200] transition-all duration-700" style="width: {{ $completion }}%"></div>
                </div>
            </div>
        </section>

        <div class="mt-5 grid grid-cols-3 gap-3">
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Total</p>
                <p class="mt-2 text-xl font-extrabold text-slate-900">{{ $target }}</p>
            </div>
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Selesai</p>
                <p class="mt-2 text-xl font-extrabold text-emerald-600">{{ $progress }}</p>
            </div>
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Kendala</p>
                <p class="mt-2 text-xl font-extrabold text-amber-600">{{ $tasks->where('current_status', 'KENDALA')->count() }}</p>
            </div>
        </div>

        <div class="mt-5 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-slate-900">Daftar PA</h2>
                <p class="text-sm text-slate-500">{{ $tasks->count() }} tugas ditugaskan hari ini</p>
            </div>
            <a href="{{ route('petugas.tasks.history') }}" class="flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 shadow-sm">Riwayat</a>
        </div>

        <section class="mt-5 rounded-2xl border border-sky-200 bg-sky-50 p-4" aria-label="Rute kerja">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-sky-700">Rute kerja hari ini</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">
                        {{ $routeStopCount }} PA yang masih perlu dikunjungi
                        @if ($nextTask) <span class="font-normal text-slate-500">· berikutnya {{ $nextTask->pa_number }}</span> @endif
                    </p>
                    <p id="route-location-status" class="mt-1 text-xs text-slate-500">
                        @if ($originLat !== null && $originLng !== null)
                            Lokasi aktif: {{ number_format($originLat, 5) }}, {{ number_format($originLng, 5) }}@if ($locationAccuracy !== null) · akurasi ±{{ round($locationAccuracy) }} m @endif
                        @else
                            Lokasi belum digunakan; urutan mengikuti data wilayah.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button id="route-location-button" type="button" class="inline-flex min-h-10 items-center rounded-xl border border-sky-200 bg-white px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100">Gunakan lokasi saya</button>
                    @if ($nextTask && $nextTaskNavigationUrl)
                        <a href="{{ $nextTaskNavigationUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center rounded-xl bg-sky-700 px-3 py-2 text-xs font-bold text-white hover:bg-sky-800">Lanjut ke PA Terdekat</a>
                    @endif
                    @if ($workRouteUrl)
                        <a href="{{ $workRouteUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center rounded-xl bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800">Buka rute lengkap ({{ min($routeStopCount, 10) }} stop)</a>
                    @endif
                    @if ($nextTaskWazeUrl)
                        <a href="{{ $nextTaskWazeUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center rounded-xl border border-sky-200 bg-white px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100">Waze</a>
                    @endif
                </div>
            </div>
        </section>

        <script>
            document.getElementById('route-location-button')?.addEventListener('click', function () {
                const button = this;
                const status = document.getElementById('route-location-status');
                if (!navigator.geolocation) {
                    status.textContent = 'Perangkat ini tidak mendukung geolocation.';
                    return;
                }

                button.disabled = true;
                button.textContent = 'Mengambil lokasi...';
                status.textContent = 'Meminta izin lokasi perangkat.';
                navigator.geolocation.getCurrentPosition(function (position) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('lat', position.coords.latitude);
                    url.searchParams.set('lng', position.coords.longitude);
                    url.searchParams.set('accuracy', position.coords.accuracy);
                    window.location.href = url.toString();
                }, function (error) {
                    button.disabled = false;
                    button.textContent = 'Coba lokasi lagi';
                    status.textContent = error.code === error.PERMISSION_DENIED
                        ? 'Izin lokasi ditolak. Rute tetap memakai koordinat wilayah.'
                        : 'Lokasi tidak berhasil diperoleh. Periksa GPS dan coba lagi.';
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
            });
        </script>

        <div class="mt-4 space-y-3">
            @forelse ($tasks as $task)
                @php $meta = $routeData[$task->id] ?? null; @endphp
                <a href="{{ $meta['detail_url'] ?? route('petugas.tasks.show', $task) }}" class="field-card app-card block p-4" data-reveal>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold tracking-[0.14em] text-sky-600">{{ $task->pa_number }}</p>
                            <div class="mt-1 flex items-center gap-1 text-xs text-slate-500">
                                <span class="font-semibold uppercase tracking-[0.1em] text-slate-400">ID Pelanggan</span>
                                <span class="text-slate-600">{{ $task->customer_id ?? '-' }}</span>
                            </div>
                        </div>
                        <x-badge-status :status="$task->current_status" />
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-sky-50 px-2.5 py-2 text-[11px] font-semibold text-sky-700">
                        <span>Rute #{{ $meta['order'] ?? '-' }}</span>
                        <span>{{ $meta['distance'] ?? 'Jarak belum dihitung' }}</span>
                    </div>

                    <div class="mt-4 flex items-start gap-2 text-sm text-slate-500">
                        <x-dashboard-icon name="location" class="mt-0.5 h-4 w-4 flex-none text-slate-400" />
                        <span class="line-clamp-2">{{ $task->address ?: 'Alamat belum tersedia' }}</span>
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs font-semibold text-slate-400">
                        <span>{{ $task->region?->kabupaten_kota ?? '-' }}</span>
                        <span class="text-sky-600">Lihat detail &rarr;</span>
                    </div>
                </a>
            @empty
                <div class="app-card px-5 py-12 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-50 text-sky-500"><x-dashboard-icon name="check" class="h-7 w-7" /></div>
                    <h3 class="mt-4 font-bold text-slate-800">Belum ada tugas hari ini</h3>
                    <p class="mt-1 text-sm text-slate-500">Tugas baru akan muncul setelah Admin melakukan assignment.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
