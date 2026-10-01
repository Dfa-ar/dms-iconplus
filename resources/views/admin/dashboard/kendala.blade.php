@extends('layouts.admin')

@php
    $active = 'dashboard';
    $title = 'Top Kendala';
    $subtitle = 'Penyebab kendala terbanyak hari ini';
@endphp

@section('title', 'Top Kendala')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-900">Top Kendala Hari Ini</h2>
            <p class="text-sm text-slate-500">Jenis kendala yang paling sering dilaporkan petugas.</p>
        </div>

        @php $maxKendala = $topKendala->max('total') ?: 1; @endphp

        @if ($topKendala->isEmpty())
            <p class="py-8 text-center text-sm text-slate-400">Belum ada kendala dilaporkan hari ini.</p>
        @else
            <div class="space-y-4">
                @foreach ($topKendala as $item)
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700">{{ $item->label }}</span>
                            <span class="text-slate-500">{{ $item->total }} kasus</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-amber-500" style="width: {{ round(($item->total / $maxKendala) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
