@props(['label', 'value', 'icon' => 'list', 'tone' => 'slate', 'suffix' => null])

@php
    $tones = [
        'slate' => ['bg' => 'bg-slate-50', 'icon' => 'text-slate-600', 'ring' => 'ring-slate-200'],
        'emerald' => ['bg' => 'bg-emerald-50', 'icon' => 'text-emerald-600', 'ring' => 'ring-emerald-200'],
        'sky' => ['bg' => 'bg-sky-50', 'icon' => 'text-sky-600', 'ring' => 'ring-sky-200'],
        'amber' => ['bg' => 'bg-amber-50', 'icon' => 'text-amber-600', 'ring' => 'ring-amber-200'],
        'red' => ['bg' => 'bg-red-50', 'icon' => 'text-red-600', 'ring' => 'ring-red-200'],
    ];
    $toneConfig = $tones[$tone] ?? $tones['slate'];
@endphp

<div class="app-card relative overflow-hidden p-4" data-reveal>
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-500 via-cyan-400 to-emerald-400"></div>
    <div class="flex items-start justify-between gap-3">
        <div>
            <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ $label }}</span>
            <p class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">
                {{ $value }}@if ($suffix)<span class="text-sm font-medium text-slate-400"> {{ $suffix }}</span>@endif
            </p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $toneConfig['bg'] }} {{ $toneConfig['icon'] }} ring-1 {{ $toneConfig['ring'] }}">
            <x-dashboard-icon :name="$icon" class="h-4 w-4" />
        </span>
    </div>
</div>
