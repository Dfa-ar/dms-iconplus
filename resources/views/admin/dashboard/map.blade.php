@extends('layouts.admin')

@php
    $active = 'map';
    $title = 'Peta Wilayah';
    $subtitle = 'Sebaran Kantor Perwakilan, petugas aktif, dan kepadatan PA per wilayah';
@endphp

@section('title', 'Peta Wilayah')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Peta Wilayah & Distribusi PA</h2>
            <p class="text-sm text-slate-500">Monitoring sebaran operasional per area kerja untuk memudahkan assignment dan kontrol tugas.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Kembali ke Control Tower
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($markers as $marker)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $marker['name'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $marker['area'] }}</p>
                    </div>
                    <span class="rounded-full bg-sky-100 px-2 py-1 text-[10px] font-semibold text-sky-700">{{ $marker['officer_count'] }} petugas</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-600">
                    <div class="rounded-lg bg-slate-50 p-2">
                        <p class="text-slate-400">Total PA</p>
                        <p class="mt-1 text-lg font-bold text-slate-800">{{ $marker['total_pa'] }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-2">
                        <p class="text-slate-400">Done</p>
                        <p class="mt-1 text-lg font-bold text-emerald-600">{{ $marker['status']['DONE'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Wilayah Operasional</h3>
                <p class="text-xs text-slate-400">Kantor, 27 wilayah administratif, petugas aktif, dan kepadatan PA.</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">Leaflet / OpenStreetMap</span>
        </div>
        <div id="map" class="h-[500px] w-full"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

    <script>
        const markers = @json($markers);
        const clusters = @json($clusters);
        const officerMarkers = @json($officerMarkers);
        const heatmap = @json($heatmap);

        const map = L.map('map', {
            zoomControl: true,
            scrollWheelZoom: true,
        }).setView([-6.9, 107.8], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(map);

        const regionLayer = L.markerClusterGroup({
            maxClusterRadius: 45,
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
        });
        const officerLayer = L.markerClusterGroup({
            maxClusterRadius: 35,
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
        });

        clusters.forEach((cluster) => {
            const radius = Math.min(32, 10 + (cluster.total_pa / 90));
            const circle = L.circleMarker([cluster.lat, cluster.lng], {
                radius,
                color: '#f59e0b',
                fillColor: '#fbbf24',
                fillOpacity: 0.42,
                weight: 1,
            });

            circle.bindPopup(`
                <div style="min-width:190px;">
                    <strong>${cluster.name}</strong><br>
                    <small>Cluster wilayah</small><br>
                    Total PA: ${cluster.total_pa}<br>
                    Petugas aktif: ${cluster.active_officer_count ?? cluster.officer_count ?? 0}<br>
                    Done: ${cluster.status.DONE}<br>
                    On Progress: ${cluster.status.ON_PROGRESS}
                </div>
            `);

            regionLayer.addLayer(circle);
        });

        markers.forEach((marker) => {
            const popup = `
                <div style="min-width:240px;">
                    <strong>${marker.name}</strong><br>
                    <small>${marker.area}</small><br>
                    Total PA: ${marker.total_pa}<br>
                    Ditugaskan: ${marker.status.ASSIGNED}<br>
                    On Progress: ${marker.status.ON_PROGRESS}<br>
                    Done: ${marker.status.DONE}<br>
                    Kendala: ${marker.status.KENDALA}<br>
                    Petugas aktif: ${marker.officer_count}<br>
                    <div style="margin-top: 8px;">
                        <a href="${marker.assignment_route}" style="display:inline-block;padding:6px 10px;border-radius:8px;background:#0ea5e9;color:#fff;text-decoration:none;">Generate Assignment</a>
                    </div>
                </div>
            `;

            const circle = L.circleMarker([marker.lat, marker.lng], {
                radius: marker.kind === 'kp' ? 11 : 9,
                color: marker.kind === 'kp' ? '#0f172a' : '#2563eb',
                fillColor: marker.kind === 'kp' ? '#38bdf8' : '#93c5fd',
                fillOpacity: 0.95,
                weight: 1.5,
            }).bindPopup(popup);

            regionLayer.addLayer(circle);
        });

        officerMarkers.forEach((officer) => {
            const marker = L.circleMarker([officer.lat, officer.lng], {
                radius: 6,
                color: '#166534',
                fillColor: '#22c55e',
                fillOpacity: 0.9,
                weight: 1.5,
            }).bindPopup(`
                <div style="min-width:190px;">
                    <strong>${officer.name}</strong><br>
                    <small>${officer.employee_code}</small><br>
                    Wilayah: ${officer.region}
                </div>
            `);
            officerLayer.addLayer(marker);
        });

        const maxPaWeight = Math.max(1, ...heatmap.map((point) => point[2]));
        const heatLayer = L.heatLayer(heatmap, {
            radius: 30,
            blur: 22,
            maxZoom: 10,
            max: maxPaWeight,
            gradient: { 0.2: '#38bdf8', 0.55: '#facc15', 0.82: '#f97316', 1: '#dc2626' },
        });

        regionLayer.addTo(map);
        heatLayer.addTo(map);
        L.control.layers(null, {
            'Kantor & wilayah': regionLayer,
            'Sebaran petugas aktif': officerLayer,
            'Kepadatan PA': heatLayer,
        }, { collapsed: false }).addTo(map);
    </script>
@endsection
