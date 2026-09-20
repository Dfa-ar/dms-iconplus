@extends('layouts.app')

@php
    $active = 'dashboard';
    $title = 'Progres Wilayah';
    $subtitle = 'Realisasi PA selesai per wilayah';
@endphp

@section('title', 'Progres Wilayah')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-900">Progress Deaktivasi per Wilayah</h2>
            <p class="text-sm text-slate-500">Persentase PA selesai dibanding total PA yang terdaftar.</p>
        </div>

        @if ($regions->isEmpty())
            <p class="py-8 text-center text-sm text-slate-400">Belum ada data wilayah.</p>
        @else
            <div class="space-y-5">
                @foreach ($regions as $region)
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700">{{ $region['wilayah'] ?? 'Tanpa Wilayah' }}</span>
                            <span class="text-slate-500">{{ $region['done'] }} / {{ $region['total'] }} PA ({{ $region['percentage'] }}%)</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-2.5 rounded-full bg-sky-500" style="width: {{ $region['percentage'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
